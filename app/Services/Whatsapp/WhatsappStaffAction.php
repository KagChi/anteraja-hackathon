<?php

declare(strict_types=1);

namespace App\Services\Whatsapp;

use App\Actions\Issues\ResolveIssue;
use App\DTOs\ResolveIssueData;
use App\Enums\IssueDecision;
use App\Enums\PackageStatus;
use App\Enums\StaffRole;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Support\PhoneFormatter;
use Throwable;

/**
 * Aksi admin yang masuk lewat balasan WhatsApp.
 *
 * Workflow n8n tidak menyentuh domain sama sekali: ia hanya mengenali niat
 * balasan lalu menyerahkannya ke sini. Setiap keputusan tetap dijalankan aksi
 * aplikasi yang sama dengan yang dipakai dashboard admin.
 *
 * Kurir tidak memakai WhatsApp: tugas dan pembaruan pengantaran hanya lewat
 * aplikasi Anteraja Kurir, jadi nomor kurir yang membalas akan ditolak.
 */
final class WhatsappStaffAction {
    public function __construct(
        private readonly ResolveIssue $resolveIssue,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function adminActions(): array {
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
                'Nomor ini belum terdaftar sebagai admin Anteraja Frozen. Hubungi admin operasional untuk mendaftarkan nomor Anda.'
            );
        }

        if ($staff->role !== StaffRole::ADMIN) {
            return $this->refused(
                'Kurir tidak bertindak lewat WhatsApp. Gunakan aplikasi Anteraja Kurir untuk menerima tugas dan memperbarui pengiriman.'
            );
        }

        $package = $this->packageFor($resi);

        if ($package === null) {
            return $this->refused('Tidak ada paket yang menunggu tindakan. Sertakan nomor resi pada balasan Anda.');
        }

        try {
            $result = $this->adminAction($staff, $package, $action, $note);
        } catch (Throwable $exception) {
            $result = $this->refused($this->readable($exception));
        }

        // Riwayat pesan menyebut siapa yang bertindak: admin.
        $result['role'] = $staff->role->value;

        return $result;
    }

    private function staffByPhone(string $phone): ?Staff {
        return Staff::query()
            ->where('is_active', true)
            ->whereIn('phone', PhoneFormatter::candidates($phone))
            ->first();
    }

    private function packageFor(?string $resi): ?Package {
        $relations = ['buyer', 'courier', 'items.product'];

        if ($resi !== null && trim($resi) !== '') {
            return Package::query()
                ->with($relations)
                ->where('tracking_number', strtoupper(trim($resi)))
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
    private function adminAction(Staff $admin, Package $package, string $action, ?string $note): array {
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

    private function openIssue(Package $package): ?IssueReport {
        return IssueReport::query()
            ->whereHas('check', fn ($query) => $query->where('package_id', $package->id))
            ->whereNull('decision')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{success: bool, message: string, template: string|null, tracking_number: string|null, role: string|null}
     */
    private function done(string $template, Package $package, string $message): array {
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
    private function refused(string $message): array {
        return [
            'success' => false,
            'role' => null,
            'template' => null,
            'tracking_number' => null,
            'message' => $message.' Anteraja Frozen ❄️',
        ];
    }

    private function readable(Throwable $exception): string {
        $message = trim($exception->getMessage());

        return $message !== ''
            ? $message
            : 'Tindakan itu belum bisa dijalankan pada paket ini. Silakan periksa status paketnya kembali.';
    }
}
