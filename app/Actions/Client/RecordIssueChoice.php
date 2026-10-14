<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Enums\BuyerIssueChoice;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Exceptions\IssueNotReported;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Client\ReplacementEligibility;
use App\Support\NotificationQueue;
use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan pilihan penyelesaian kendala yang dikirim pembeli lewat WhatsApp
 * (opsi 1-4 pada pesan "Konfirmasi Penyelesaian Kendala ke Pembeli").
 */
final class RecordIssueChoice
{
    public function __construct(
        private readonly NotificationQueue $notificationQueue,
        private readonly RequestReplacement $requestReplacement,
        private readonly ReplacementEligibility $eligibility,
    ) {}

    /**
     * @return array{choice: BuyerIssueChoice, already_recorded: bool, replacement: Package|null}
     */
    public function execute(Package $package, BuyerIssueChoice $choice): array
    {
        return DB::transaction(function () use ($package, $choice): array {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            /** @var IssueReport|null $issue */
            $issue = IssueReport::query()
                ->whereHas('check', fn ($query) => $query->where('package_id', $locked->id))
                ->latest('reported_at')
                ->lockForUpdate()
                ->first();

            if ($issue === null) {
                throw new IssueNotReported;
            }

            if ($issue->buyer_choice !== null) {
                // Pilihan pertama yang menang; balasan berulang tidak mengubah apa pun.
                return [
                    'choice' => $issue->buyer_choice,
                    'already_recorded' => true,
                    'replacement' => $this->createReplacementWhenEligible($locked, $choice),
                ];
            }

            $issue->update([
                'buyer_choice' => $choice->value,
                'buyer_responded_at' => now(),
            ]);

            $this->notifyAdmins($locked, $choice, $issue);

            return [
                'choice' => $choice,
                'already_recorded' => false,
                // Opsi 2 memakai alur pengiriman ulang yang sudah ada.
                'replacement' => $this->createReplacementWhenEligible($locked, $choice),
            ];
        });
    }

    /**
     * Opsi 2 memakai alur pengiriman ulang yang sudah ada, tetapi hanya untuk
     * paket yang sudah memenuhi syarat retur. Bila belum, pilihan pembeli tetap
     * dicatat dan admin melanjutkan keputusan kendalanya.
     */
    private function createReplacementWhenEligible(Package $package, BuyerIssueChoice $choice): ?Package
    {
        if ($choice !== BuyerIssueChoice::RESEND) {
            return null;
        }

        $eligibility = $this->eligibility->check($package);

        if (! $eligibility->isEligible && $eligibility->childPackage === null) {
            return null;
        }

        return $this->requestReplacement->execute($package);
    }

    private function notifyAdmins(Package $package, BuyerIssueChoice $choice, IssueReport $issue): void
    {
        $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $category = $issue->issue_category;

        foreach ($admins as $admin) {
            $this->notificationQueue->queue(
                $package,
                RecipientType::ADMIN,
                $admin->id,
                'buyer_issue_choice',
                [
                    'tracking_number' => $package->tracking_number,
                    'choice' => $choice->value,
                    'choice_label' => $choice->label(),
                    'issue_category' => $category instanceof BackedEnum ? $category->value : (string) $category,
                ]
            );
        }
    }
}
