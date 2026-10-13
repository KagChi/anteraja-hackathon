<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\Enums\PackageStatus;
use App\Exceptions\TaskNotActionable;
use App\Models\Package;
use App\Models\Staff;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Facades\DB;

final class RejectTask
{
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {}

    public function execute(Package $package, Staff $courier, ?string $reason = null): Package
    {
        return DB::transaction(function () use ($package, $courier, $reason) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courier->id || $locked->status !== PackageStatus::COURIER_ASSIGNED) {
                throw TaskNotActionable::notAssignable($locked->tracking_number);
            }

            if ($locked->courier_accepted_at !== null) {
                throw TaskNotActionable::alreadyAccepted();
            }

            $this->transitioner->transition(
                $locked,
                PackageStatus::CONFIRMED,
                $courier,
                $reason ?? 'Kurir menolak penugasan'
            );

            $locked->update([
                'courier_id' => null,
                'courier_accepted_at' => null,
            ]);

            $this->notifications->queueTaskRejected($locked, $courier, $reason);

            return $locked;
        });
    }
}
