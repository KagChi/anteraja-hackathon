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

final class ProceedDecisionHandler
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function handle(Package $package, IssueReport $issueReport, ResolveIssueData $data, Staff $admin): void
    {
        $this->transitioner->transition(
            package: $package,
            to: PackageStatus::IN_DELIVERY,
            actor: $admin
        );

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
                    'instructions' => 'Lanjutkan pengantaran paket kepada penerima.',
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
                'message' => 'Pengiriman dilanjutkan menuju alamat Anda.',
            ]
        );
    }
}
