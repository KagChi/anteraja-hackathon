<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RescheduleShipment
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function execute(
        Package $package,
        CarbonImmutable $newScheduledAt,
        CarbonImmutable $newScheduledEndAt,
        Staff $admin
    ): Package {
        return DB::transaction(function () use ($package, $newScheduledAt, $newScheduledEndAt, $admin) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PackageStatus::RESCHEDULE_REQUESTED) {
                throw new InvalidArgumentException('Paket tidak dalam status menunggu penjadwalan ulang.');
            }

            $locked->scheduled_at = $newScheduledAt;
            $locked->scheduled_end_at = $newScheduledEndAt;
            $locked->save();

            ScheduleConfirmation::create([
                'package_id' => $locked->id,
                'response' => 'PENDING',
                'requested_at' => now(),
            ]);

            $updated = $this->transitioner->transition(
                $locked,
                PackageStatus::AWAITING_CONFIRMATION,
                $admin
            );

            // Queue notification to the recipient: the delivery leg is their
            // conversation, and their answer rides on the package token rather
            // than on a customer app link.
            $this->notificationQueue->queue(
                $updated,
                RecipientType::BUYER,
                $updated->buyer_id,
                'schedule_rescheduled',
                [
                    'tracking_number' => $updated->tracking_number,
                    'window' => DisplayTime::windowLabel($newScheduledAt, $newScheduledEndAt),
                ]
            );

            return $updated->refresh();
        });
    }
}
