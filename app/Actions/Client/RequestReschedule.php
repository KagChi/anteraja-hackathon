<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\DTOs\Client\RescheduleRequestData;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Exceptions\ScheduleNotActionable;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Facades\DB;

final class RequestReschedule
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notificationQueue,
    ) {}

    public function execute(Package $package, RescheduleRequestData $data): Package
    {
        return DB::transaction(function () use ($package, $data) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            // Idempotent double-tap: the buyer's window is already applied.
            if ($locked->status === PackageStatus::CONFIRMED) {
                return $locked;
            }

            if ($locked->status !== PackageStatus::AWAITING_CONFIRMATION) {
                throw new ScheduleNotActionable('Status paket tidak dapat dijadwalkan ulang.');
            }

            /** @var ScheduleConfirmation|null $pending */
            $pending = $locked->scheduleConfirmations()
                ->where('response', 'PENDING')
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                throw new ScheduleNotActionable('Tidak ada jadwal yang menunggu konfirmasi.');
            }

            $previousScheduledAt = $locked->scheduled_at;

            $pending->update([
                'response' => 'RESCHEDULE_REQUESTED',
                'proposed_scheduled_at' => $data->proposedScheduledAt,
                'responded_at' => now(),
            ]);

            // The buyer picked the window themselves, so it is applied right
            // away: the parcel moves to the new date without waiting on an
            // admin to re-propose it. CONFIRMED means "scheduled and agreed".
            $updated = $this->transitioner->transition($locked, PackageStatus::CONFIRMED, null, [
                'scheduled_at' => $data->proposedScheduledAt,
                'scheduled_end_at' => $data->proposedScheduledEndAt,
            ]);

            // Admins stay in the loop; the change is already live.
            $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();
            foreach ($admins as $admin) {
                $this->notificationQueue->queue(
                    $updated,
                    RecipientType::ADMIN,
                    $admin->id,
                    'reschedule_requested',
                    [
                        'tracking_number' => $updated->tracking_number,
                        'proposed_scheduled_at' => $data->proposedScheduledAt->toIso8601String(),
                        // The admin reads the new window on WhatsApp, so the
                        // label travels with the ISO timestamp the console
                        // keeps using.
                        'proposed_window' => DisplayTime::dateTimeIndo($data->proposedScheduledAt),
                        'current_scheduled_at' => $previousScheduledAt?->toIso8601String(),
                    ]
                );
            }

            return $updated;
        });
    }
}
