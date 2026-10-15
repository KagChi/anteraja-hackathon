<?php

declare(strict_types=1);

namespace App\Actions\Issues\Decisions;

use App\DTOs\ResolveIssueData;
use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Models\IssueReport;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Carbon\Carbon;

final class RescheduleDecisionHandler {
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {
    }

    public function handle(Package $package, IssueReport $issueReport, ResolveIssueData $data, Staff $admin): void {
        $this->transitioner->transition(
            package: $package,
            to: PackageStatus::AWAITING_CONFIRMATION,
            actor: $admin,
            attributes: [
                'courier_id' => null,
                'courier_accepted_at' => null,
            ]
        );

        // Insert new PENDING schedule confirmation
        ScheduleConfirmation::create([
            'package_id' => $package->id,
            'response' => ConfirmationResponse::PENDING,
            'proposed_scheduled_at' => $package->scheduled_at,
            'requested_at' => Carbon::now('UTC'),
        ]);

        $this->notificationQueue->queue(
            package: $package,
            type: RecipientType::BUYER,
            recipientId: $package->buyer_id,
            template: 'schedule_request',
            payload: [
                'tracking_number' => $package->tracking_number,
                'scheduled_at' => $package->scheduled_at?->toIso8601String(),
                'scheduled_end_at' => $package->scheduled_end_at?->toIso8601String(),
            ]
        );
    }
}
