<?php

declare(strict_types=1);

namespace App\Actions\Issues\Decisions;

use App\DTOs\ResolveIssueData;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\Staff;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use App\Support\ReplacementPackageFactory;

final class ResendDecisionHandler
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
        private readonly ReplacementPackageFactory $packageFactory,
    ) {}

    public function handle(Package $package, IssueReport $issueReport, ResolveIssueData $data, Staff $admin): Package
    {
        // 1. Transition original package to RETURNED
        $this->transitioner->transition(
            package: $package,
            to: PackageStatus::RETURNED,
            actor: $admin
        );

        // 2. Create new replacement package via unified factory
        $newPackage = $this->packageFactory->from($package, $admin);

        // 3. Queue notification for courier if assigned
        if ($package->courier_id !== null) {
            $this->notificationQueue->queue(
                package: $package,
                type: RecipientType::COURIER,
                recipientId: $package->courier_id,
                template: 'issue_decision',
                payload: [
                    'tracking_number' => $package->tracking_number,
                    'decision' => $data->decision->value,
                    'decision_reason' => $data->decisionReason,
                    'instructions' => "Paket asal dikembalikan ke DC. Resi pengganti: {$newPackage->tracking_number}.",
                ]
            );
        }

        $this->notificationQueue->queue(
            package: $package,
            type: RecipientType::BUYER,
            recipientId: $package->buyer_id,
            template: 'issue_decision_update',
            payload: [
                'tracking_number' => $package->tracking_number,
                'decision' => $data->decision->value,
                'message' => "Pengiriman ulang disiapkan. Resi pengganti: {$newPackage->tracking_number}.",
                'replacement_tracking_number' => $newPackage->tracking_number,
            ]
        );

        return $newPackage;
    }
}
