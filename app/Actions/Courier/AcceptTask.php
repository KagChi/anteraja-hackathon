<?php

declare(strict_types=1);

namespace App\Actions\Courier;

use App\Enums\PackageStatus;
use App\Exceptions\TaskNotActionable;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AcceptTask
{
    public function execute(Package $package, Staff $courier): Package
    {
        return DB::transaction(function () use ($package, $courier) {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($locked->courier_id !== $courier->id || $locked->status !== PackageStatus::COURIER_ASSIGNED) {
                throw TaskNotActionable::notAssignable($locked->tracking_number);
            }

            if ($locked->courier_accepted_at !== null) {
                return $locked;
            }

            $locked->update([
                'courier_accepted_at' => Carbon::now(),
            ]);

            return $locked;
        });
    }
}
