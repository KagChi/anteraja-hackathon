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

final class RecheckDecisionHandler
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function handle(Package $package, IssueReport $issueReport, ResolveIssueData $data, Staff $admin): void
    {
        $this->transitioner->transition(
            package: $package,
            to: PackageStatus::CHECKED,
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
                    'instructions' => 'Lakukan pemeriksaan ulang kondisi fisik paket.',
                ]
            );
        }

        // The buyer chose a resolution and is waiting on the outcome: tell them
        // it is being re-inspected, which also moves the WhatsApp conversation
        // off the resolution menu.
        $this->notificationQueue->queue(
            package: $package,
            type: RecipientType::BUYER,
            recipientId: $package->buyer_id,
            template: 'issue_decision_update',
            payload: [
                'tracking_number' => $package->tracking_number,
                'decision' => $data->decision->value,
                'message' => 'Paket sedang diperiksa ulang oleh tim operasional kami.',
            ]
        );
    }
}
