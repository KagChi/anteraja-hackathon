<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Enums\PackageStatus;
use App\Exceptions\PackageNotAssignable;
use App\Models\Package;
use App\Models\Staff;
use App\Support\PackageStatusTransitioner;
use Illuminate\Support\Facades\DB;

final class AssignCourier {
    /**
     * Statuses a courier can still be (re)assigned from. Assignment now comes
     * before the receiving-date confirmation, so a freshly created parcel is
     * handed over while it is still CREATED. Once the parcel is on the road the
     * courier is part of the delivery record, so a swap has to go through the
     * delivery flow instead of the assignment dialog.
     */
    private const ASSIGNABLE_STATUSES = [
        PackageStatus::CREATED,
        PackageStatus::CONFIRMED,
        PackageStatus::COURIER_ASSIGNED,
        PackageStatus::CHECKED,
        PackageStatus::ISSUE_REPORTED,
    ];

    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
    ) {
    }

    public function execute(Package $package, Staff $courier, Staff $admin): Package {
        return DB::transaction(function () use ($package, $courier, $admin): Package {
            // Pessimistic locking to prevent race conditions
            /** @var Package $lockedPackage */
            $lockedPackage = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPackage->status, self::ASSIGNABLE_STATUSES, true)) {
                throw new PackageNotAssignable;
            }

            if (! $courier->isCourier()) {
                throw new PackageNotAssignable('Staf yang dipilih bukan kurir aktif.');
            }

            // Re-sending the task to the courier who already holds it would
            // reset the acceptance their app recorded, so only a parcel that
            // still has to be handed over runs through the transition.
            if ($lockedPackage->courier_id === $courier->id
                && $lockedPackage->status !== PackageStatus::CREATED
                && $lockedPackage->status !== PackageStatus::CONFIRMED) {
                return $lockedPackage;
            }

            return $this->handOver($lockedPackage, $courier, $admin);
        });
    }

    /**
     * Put the parcel in the chosen courier's hands: a CREATED or CONFIRMED
     * parcel moves to COURIER_ASSIGNED, while one that is already further along
     * (moved before departure, checked, or flagged) keeps its status and only
     * changes hands — the status matrix has no CHECKED or ISSUE_REPORTED edge
     * back to COURIER_ASSIGNED. Clearing the acceptance makes the new courier
     * take the task again.
     */
    private function handOver(Package $package, Staff $courier, Staff $admin): Package {
        if ($package->status === PackageStatus::CREATED || $package->status === PackageStatus::CONFIRMED) {
            return $this->transitioner->transition(
                package: $package,
                to: PackageStatus::COURIER_ASSIGNED,
                actor: $admin,
                attributes: [
                    'courier_id' => $courier->id,
                    'courier_accepted_at' => null,
                ]
            );
        }

        $package->courier_id = $courier->id;
        $package->courier_accepted_at = null;
        $package->save();

        return $package->refresh();
    }
}
