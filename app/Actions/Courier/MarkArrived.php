<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\Enums\DeliveryMilestoneEnum;
use App\Enums\PackageStatus;
use App\Exceptions\DeliveryNotActionable;
use App\Models\DeliveryMilestone;
use App\Models\Package;
use App\Models\Staff;
use App\Support\NotificationQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MarkArrived
{
    public function __construct(
        private readonly NotificationQueue $notifications,
    ) {}

    public function execute(Package $package, Staff $courier, ?float $latitude = null, ?float $longitude = null): Package
    {
        return DB::transaction(function () use ($package, $courier, $latitude, $longitude) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courier->id) {
                throw DeliveryNotActionable::invalidState('Paket tidak ditugaskan ke kurir ini.');
            }

            if ($locked->status !== PackageStatus::IN_DELIVERY) {
                throw DeliveryNotActionable::invalidState('Paket tidak dalam proses pengantaran.');
            }

            $latestMilestone = DeliveryMilestone::where('package_id', $locked->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($latestMilestone?->milestone === DeliveryMilestoneEnum::ARRIVED) {
                return $locked;
            }

            if ($latestMilestone?->milestone !== DeliveryMilestoneEnum::DEPARTED) {
                throw DeliveryNotActionable::invalidState('Kurir belum memulai perjalanan (DEPARTED).');
            }

            DeliveryMilestone::create([
                'package_id' => $locked->id,
                'milestone' => DeliveryMilestoneEnum::ARRIVED,
                'courier_id' => $courier->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'occurred_at' => Carbon::now(),
            ]);

            $this->notifications->queueCourierArrived($locked, $courier);

            return $locked;
        });
    }
}
