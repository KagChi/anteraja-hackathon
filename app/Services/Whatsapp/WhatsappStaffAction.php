<?php

declare(strict_types=1);

namespace App\Services\Whatsapp;

use App\Actions\Courier\AcceptTask;
use App\Actions\Courier\ConfirmDelivery;
use App\Actions\Courier\MarkArrived;
use App\Actions\Courier\RejectTask;
use App\Actions\Courier\ReportIssue;
use App\Actions\Courier\StartDelivery;
use App\Actions\Issues\ResolveIssue;
use App\DTOs\Courier\ConfirmDeliveryData;
use App\DTOs\Courier\ReportIssueData;
use App\DTOs\Courier\StartDeliveryData;
use App\DTOs\ResolveIssueData;
use App\Enums\IssueCategory;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\RecipientRelation;
use App\Enums\StaffRole;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Support\ChecklistNormalizer;
use App\Support\PhoneFormatter;
use Throwable;

/**
 * Aksi kurir dan admin yang masuk lewat balasan WhatsApp.
 *
 * Workflow n8n tidak menyentuh domain sama sekali: ia hanya mengenali niat
 * balasan lalu menyerahkannya ke sini. Setiap langkah tetap dijalankan aksi
 * aplikasi yang sama dengan yang dipakai aplikasi kurir dan dashboard admin,
 * jadi status paket, riwayat, dan notifikasi lanjutan tidak bisa berbeda
 * tergantung kanal yang dipakai.
 */
final class WhatsappStaffAction
{
    public function __construct(
        private readonly AcceptTask $acceptTask,
        private readonly RejectTask $rejectTask,
        private readonly StartDelivery $startDelivery,
        private readonly MarkArrived $markArrived,
        private readonly ConfirmDelivery $confirmDelivery,
        private readonly ReportIssue $reportIssue,
        private readonly ResolveIssue $resolveIssue,
    ) {}

    /**
     * @return list<string>
     */
    public static function courierActions(): array
    {
        return ['accept', 'reject', 'depart', 'arrived', 'deliver', 'issue'];
    }

    /**
     * @return list<string>
     */
    public static function adminActions(): array
    {
        return ['return', 'resend', 'proceed', 'recheck', 'reschedule'];
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    public function handle(
        string $phone,
        string $action,
        ?string $resi = null,
        ?string $note = null,
        ?string $category = null,
    ): array {
        $staff = $this->staffByPhone($phone);

        if ($staff === null) {
            return $this->refused(
                'Nomor ini belum terdaftar sebagai kurir atau admin Anteraja Frozen. Hubungi admin operasional untuk mendaftarkan nomor Anda.'
            );
        }

        $package = $this->packageFor($staff, $resi);

        if ($package === null) {
            return $this->refused(
                $staff->role === StaffRole::COURIER
                    ? 'Tidak ada paket aktif yang ditugaskan ke nomor ini. Balas dengan nomor resi yang ingin Anda tangani.'
                    : 'Tidak ada paket yang menunggu tindakan. Sertakan nomor resi pada balasan Anda.'
            );
        }

        try {
            $result = $staff->role === StaffRole::COURIER
                ? $this->courierAction($staff, $package, $action, $note, $category)
                : $this->adminAction($staff, $package, $action, $note);
        } catch (Throwable $exception) {
            $result = $this->refused($this->readable($exception));
        }

        // Riwayat pesan menyebut siapa yang bertindak: kurir atau admin.
        $result['role'] = $staff->role->value;

        return $result;
    }

    private function staffByPhone(string $phone): ?Staff
    {
        return Staff::query()
            ->where('is_active', true)
            ->whereIn('phone', PhoneFormatter::candidates($phone))
            ->first();
    }

    private function packageFor(Staff $staff, ?string $resi): ?Package
    {
        $relations = ['buyer', 'courier', 'items.product'];

        if ($resi !== null && trim($resi) !== '') {
            $package = Package::query()
                ->with($relations)
                ->where('tracking_number', strtoupper(trim($resi)))
                ->first();

            if ($package === null) {
                return null;
            }

            // Kurir hanya boleh menyentuh paket yang ditugaskan kepadanya.
            return $staff->role === StaffRole::COURIER && $package->courier_id !== $staff->id
                ? null
                : $package;
        }

        if ($staff->role === StaffRole::COURIER) {
            return Package::query()
                ->with($relations)
                ->where('courier_id', $staff->id)
                ->whereIn('status', [
                    PackageStatus::COURIER_ASSIGNED->value,
                    PackageStatus::CHECKED->value,
                    PackageStatus::IN_DELIVERY->value,
                ])
                ->orderByDesc('id')
                ->first();
        }

        return Package::query()
            ->with($relations)
            ->where('status', PackageStatus::ISSUE_REPORTED->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    private function courierAction(Staff $courier, Package $package, string $action, ?string $note, ?string $category): array
    {
        $resi = $package->tracking_number;

        switch ($action) {
            case 'accept':
                $this->acceptTask->execute($package, $courier);

                return $this->accepted($package);

            case 'reject':
                $this->rejectTask->execute($package, $courier, $note);

                return $this->done('courier_rejected', $package, 'Baik, penugasan untuk resi '.$resi.' sudah Anda tolak'
                    .($note !== null && trim($note) !== '' ? ' dengan alasan: '.trim($note).'.' : '.')
                    .' Admin sudah diberi tahu untuk menugaskan kurir lain. Terima kasih.');

            case 'depart':
                $this->startDelivery->execute($package, $courier, new StartDeliveryData(
                    checklist: ChecklistNormalizer::normalize([]),
                ));

                return $this->done('courier_departed', $package, 'Pengantaran paket '.$resi.' sudah dimulai, dan pembeli menerima informasi bahwa paket sedang diantar. Hati-hati di jalan.');

            case 'arrived':
                $this->markArrived->execute($package, $courier);

                return $this->done('courier_arrived', $package, 'Kedatangan Anda untuk paket '.$resi.' sudah dicatat dan pembeli sudah diberi tahu. Mohon siapkan serah terima paket beku.');

            case 'deliver':
                $receivedBy = $note !== null && trim($note) !== '' ? trim($note) : ($package->buyer?->name ?? 'Penerima');

                $this->confirmDelivery->execute($package, $courier, new ConfirmDeliveryData(
                    receivedBy: $receivedBy,
                    recipientRelation: RecipientRelation::SELF,
                    photoPath: '',
                    receiptNote: 'Serah terima dicatat dari balasan WhatsApp kurir.',
                ));

                return $this->done('courier_delivered', $package, 'Serah terima paket '.$resi.' kepada '.$receivedBy.' sudah dicatat dan pengiriman ditutup. Terima kasih.');

            case 'issue':
                $issueCategory = IssueCategory::tryFrom((string) $category) ?? IssueCategory::KEMASAN_BOCOR;

                $this->reportIssue->execute($package, $courier, new ReportIssueData(
                    issueCategory: $issueCategory,
                    description: $note !== null && trim($note) !== '' ? trim($note) : 'Dilaporkan kurir lewat WhatsApp',
                    photoPath: '',
                    checklist: ChecklistNormalizer::normalize([$issueCategory->value => true]),
                ));

                return $this->done('courier_issue_reported', $package, 'Laporan kendala paket '.$resi.' ('.$issueCategory->label().') sudah kami catat. Admin akan memutuskan tindakannya dan pembeli menerima pilihan penyelesaian kendala.');

            default:
                return $this->refused('Perintah "'.$action.'" tidak dikenal untuk kurir. Balas *TERIMA* untuk menerima tugas, *TOLAK* untuk menolak, *BERANGKAT*, *TIBA*, *SELESAI*, atau *BOCOR* bila ada kendala.');
        }
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    private function adminAction(Staff $admin, Package $package, string $action, ?string $note): array
    {
        $decision = match ($action) {
            'return' => IssueDecision::RETURN,
            'resend' => IssueDecision::RESEND,
            'proceed' => IssueDecision::PROCEED,
            'recheck' => IssueDecision::RECHECK,
            'reschedule' => IssueDecision::RESCHEDULE,
            default => null,
        };

        if ($decision === null) {
            return $this->refused('Keputusan "'.$action.'" tidak dikenal. Balas *KEMBALIKAN*, *KIRIM ULANG*, *LANJUT*, *PERIKSA ULANG*, atau *JADWALKAN ULANG* beserta nomor resinya.');
        }

        $issue = $this->openIssue($package);

        if ($issue === null) {
            return $this->refused('Paket '.$package->tracking_number.' tidak sedang menunggu keputusan kendala.');
        }

        $this->resolveIssue->execute($package, $issue, new ResolveIssueData(
            decision: $decision,
            decisionReason: $note !== null && trim($note) !== ''
                ? trim($note)
                : 'Diputuskan admin lewat WhatsApp',
        ), $admin);

        return $this->done(
            'admin_decision:'.$decision->value,
            $package,
            'Keputusan "'.$decision->label().'" untuk resi '.$package->tracking_number.' sudah dijalankan. Instruksi dikirim ke kurir dan pembeli menerima informasi keputusannya.'
        );
    }

    private function openIssue(Package $package): ?IssueReport
    {
        return IssueReport::query()
            ->whereHas('check', fn ($query) => $query->where('package_id', $package->id))
            ->whereNull('decision')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    private function accepted(Package $package): array
    {
        $instructions = rtrim(trim((string) $package->handling_instructions), '.');

        $message = 'Tugas pengantaran paket '.$package->tracking_number.' sudah Anda terima.';

        if ($instructions !== '') {
            $message .= ' Instruksi penanganan: '.$instructions.'.';
        }

        return $this->done(
            'courier_accepted',
            $package,
            $message.' Mohon periksa kemasan sebelum berangkat, lalu balas *BERANGKAT* saat mulai mengantar.'
        );
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    private function done(string $template, Package $package, string $message): array
    {
        return [
            'success' => true,
            'role' => 'STAFF',
            'template' => $template,
            'tracking_number' => $package->tracking_number,
            'message' => $message.' Anteraja Frozen ❄️',
        ];
    }

    /**
     * @return array{success: false, message: string, template: null, tracking_number: null, role: null}
     */
    private function refused(string $message): array
    {
        return [
            'success' => false,
            'role' => null,
            'template' => null,
            'tracking_number' => null,
            'message' => $message.' Anteraja Frozen ❄️',
        ];
    }

    private function readable(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== ''
            ? $message
            : 'Tindakan itu belum bisa dijalankan pada paket ini. Silakan periksa status paketnya kembali.';
    }
}
