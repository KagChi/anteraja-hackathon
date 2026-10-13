<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Exceptions\ScheduleNotActionable;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Facades\DB;

final class ConfirmSchedule
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner
    ) {}

    public function execute(Package $package): Package
    {
        return DB::transaction(function () use ($package) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            // Double-tap idempotency
            if ($locked->status === PackageStatus::CONFIRMED) {
                $latest = $locked->scheduleConfirmations()->latest('id')->first();
                $isReady = $latest !== null && (
                    $latest->response === ConfirmationResponse::READY
                    || $latest->response?->value === 'READY'
                    || (string) $latest->response === 'READY'
                );
                if ($isReady) {
                    return $locked;
                }
            }

            if ($locked->status !== PackageStatus::AWAITING_CONFIRMATION) {
                throw new ScheduleNotActionable('Status paket tidak dapat dikonfirmasi.');
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
                'response' => 'READY',
                'responded_at' => now(),
            ]);

            return $this->transitioner->transition($locked, PackageStatus::CONFIRMED, null);
        });
    }
}
