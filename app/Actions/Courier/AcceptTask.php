<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Exceptions\TaskNotActionable;
use App\Models\Package;
use App\Models\Staff;
use App\Support\DisplayTime;
use App\Support\NotificationQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AcceptTask
{
    public function __construct(
        private readonly NotificationQueue $notifications,
    ) {}

    public function execute(Package $package, Staff $courier): Package
    {
        /** @var Package|null $accepted */
        $accepted = DB::transaction(function () use ($package, $courier): ?Package {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courier->id || $locked->status !== PackageStatus::COURIER_ASSIGNED) {
                throw TaskNotActionable::notAssignable($locked->tracking_number);
            }

            if ($locked->courier_accepted_at !== null) {
                return null;
            }

            $locked->update([
                'courier_accepted_at' => Carbon::now(),
            ]);

            return $locked;
        });

        if ($accepted === null) {
            // Sudah diterima sebelumnya: tidak ada pengingat kedua.
            return $package->refresh();
        }

        // Kurir yang baru menerima tugas diingatkan memeriksa kemasan sebelum
        // berangkat, lewat kanal yang sama dengan tugasnya.
        $this->notifications->queue(
            $accepted,
            RecipientType::COURIER,
            $courier->id,
            'condition_check_reminder',
            [
                'window' => DisplayTime::windowLabel($accepted->scheduled_at, $accepted->scheduled_end_at),
            ]
        );

        return $accepted;
    }
}
