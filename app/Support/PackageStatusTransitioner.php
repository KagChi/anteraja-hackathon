<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PackageStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PackageStatusTransitioner
{
    /**
     * @param  array<string, mixed>|string  $attributes
     */
    public function transition(
        Package $package,
        PackageStatus $to,
        ?Staff $actor = null,
        array|string $attributes = []
    ): Package {
        $extraAttributes = is_array($attributes) ? $attributes : [];

        $execute = function () use ($package, $to, $actor, $extraAttributes): Package {
            $fromStatus = $package->status instanceof PackageStatus ? $package->status->value : (string) $package->status;

            // Set actor context in PostgreSQL session for trigger status_logs.changed_by
            if ($actor !== null) {
                DB::statement("SELECT set_config('app.staff_id', ?, true)", [(string) $actor->id]);
            } else {
                DB::statement("SELECT set_config('app.staff_id', '', true)");
            }

            try {
                $package->status = $to;
                foreach ($extraAttributes as $key => $value) {
                    $package->setAttribute($key, $value);
                }
                $package->save();
            } catch (QueryException $e) {
                $sqlState = (string) $e->getCode();
                if ($sqlState === '23514' || str_contains($e->getMessage(), 'Invalid package status transition')) {
                    throw new InvalidStatusTransition(
                        fromStatus: $fromStatus,
                        toStatus: $to->value,
                        message: "Invalid package status transition: {$fromStatus} -> {$to->value}",
                        previous: $e
                    );
                }

                throw $e;
            }

            return $package->refresh();
        };

        if (DB::transactionLevel() === 0) {
            return DB::transaction($execute);
        }

        return $execute();
    }
}
