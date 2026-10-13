<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Services\Client\ClientLinkService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReplacementPackageFactory
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
        private readonly ClientLinkService $linkService,
    ) {}

    public function from(Package $original, ?Staff $actor = null): Package
    {
        return DB::transaction(function () use ($original, $actor) {
            $origScheduledAt = $original->scheduled_at !== null
                ? CarbonImmutable::instance($original->scheduled_at)
                : now()->toImmutable();

            $origScheduledEndAt = $original->scheduled_end_at !== null
                ? CarbonImmutable::instance($original->scheduled_end_at)
                : $origScheduledAt->addHours(2);

            // Same window next day (or tomorrow if past)
            $newScheduledAt = $origScheduledAt->addDay();
            if ($newScheduledAt->isPast()) {
                $newScheduledAt = now()->addDay()->toImmutable()
                    ->setTime($origScheduledAt->hour, $origScheduledAt->minute, 0);
            }

            $newScheduledEndAt = $newScheduledAt
                ->setTime($origScheduledEndAt->hour, $origScheduledEndAt->minute, 0);

            /** @var Package $replacement */
            $replacement = Package::create([
                'buyer_id' => $original->buyer_id,
                'created_by' => $original->created_by,
                'package_category' => $original->package_category,
                'weight_kg' => $original->weight_kg,
                'service_type' => $original->service_type,
                'koli_count' => $original->koli_count,
                'handling_instructions' => $original->handling_instructions,
                'resend_of_package_id' => $original->id,
                'cooler_box_code' => null,
                'courier_id' => null,
                'courier_accepted_at' => null,
                'status' => PackageStatus::CREATED,
                'scheduled_at' => $newScheduledAt,
                'scheduled_end_at' => $newScheduledEndAt,
            ]);

            $replacement->refresh();

            // Copy items
            foreach ($original->items as $item) {
                PackageItem::create([
                    'package_id' => $replacement->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
            }

            // Transition CREATED -> AWAITING_CONFIRMATION
            $replacement = $this->transitioner->transition(
                $replacement,
                PackageStatus::AWAITING_CONFIRMATION,
                $actor
            );

            // Create pending schedule confirmation
            ScheduleConfirmation::create([
                'package_id' => $replacement->id,
                'response' => 'PENDING',
                'requested_at' => now(),
            ]);

            // Queue notifications
            $clientUrl = $this->linkService->entry($replacement);

            $this->notificationQueue->queue(
                $replacement,
                RecipientType::BUYER,
                $replacement->buyer_id,
                'schedule_request',
                [
                    'tracking_number' => $replacement->tracking_number,
                    'client_url' => $clientUrl,
                    'window' => DisplayTime::windowLabel($newScheduledAt, $newScheduledEndAt),
                ]
            );

            $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();
            foreach ($admins as $admin) {
                $this->notificationQueue->queue(
                    $replacement,
                    RecipientType::ADMIN,
                    $admin->id,
                    'resend_requested',
                    [
                        'original_tracking_number' => $original->tracking_number,
                        'replacement_tracking_number' => $replacement->tracking_number,
                    ]
                );
            }

            return $replacement->refresh();
        });
    }
}
