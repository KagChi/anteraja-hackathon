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

            // Idempotent double-tap
            if ($locked->status === PackageStatus::RESCHEDULE_REQUESTED) {
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

            $pending->update([
                'response' => 'RESCHEDULE_REQUESTED',
                'proposed_scheduled_at' => $data->proposedScheduledAt,
                'responded_at' => now(),
            ]);

            $updated = $this->transitioner->transition($locked, PackageStatus::RESCHEDULE_REQUESTED, null);

            // Notify active admins
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
                        // The admin reads the requested window on WhatsApp, so
                        // the label travels with the ISO timestamp the console
                        // keeps using.
                        'proposed_window' => DisplayTime::dateTimeIndo($data->proposedScheduledAt),
                        'current_scheduled_at' => $updated->scheduled_at?->toIso8601String(),
                    ]
                );
            }

            return $updated;
        });
    }
}
