<?php

declare(strict_types=1);

namespace App\Services\Gps;

use App\DTOs\Courier\GpsAssessment;
use App\DTOs\Courier\GpsSignalData;
use App\Enums\GpsAction;
use App\Enums\GpsLevel;
use App\Enums\GpsLockStatus;
use App\Exceptions\FakeGpsSuspected;
use App\Exceptions\OutsideGeofence;
use App\Models\GpsLockRequest;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Models\Staff;

/**
 * The one door every courier GPS verdict goes through.
 *
 * It runs the detector and the geofence, writes the evidence row, opens a
 * review request when the action is refused, and throws the exception the API
 * renders. An approved request for the same package and action is what lets the
 * retry through, and the approval id is recorded on the evidence row.
 */
final class GpsValidator
{
    public function __construct(
        private readonly FakeGpsDetector $detector,
        private readonly GeofenceEvaluator $geofence,
    ) {}

    /**
     * @throws FakeGpsSuspected
     * @throws OutsideGeofence
     */
    public function validate(
        Package $package,
        ?Staff $courier,
        GpsAction $action,
        GpsSignalData $signals,
        ?float $latitude,
        ?float $longitude,
        bool $enforceGeofence,
    ): ?LocationCheck {
        // No coordinates means nothing to judge. GPS is best effort: a courier
        // with a dead GPS receiver can still work, the hub just gets no
        // location evidence and no block.
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $assessment = $this->detector->assess($package, $signals);
        $package->loadMissing('buyer');

        $geo = $this->geofence->evaluate($package->buyer, $latitude, $longitude, $package->service_type);

        // Arriving is allowed from anywhere (the courier may still be walking),
        // so only the hand-over enforces the radius.
        $geofenceBlocked = $enforceGeofence && $geo['inside'] === false;
        $wouldBlock = $assessment->blocked() || $geofenceBlocked;

        $override = $wouldBlock ? $this->approvedOverride($package, $action) : null;
        $blocked = $wouldBlock && $override === null;

        $lockRequest = $blocked
            ? $this->openLockRequest($package, $courier, $action, $assessment, $geo, $latitude, $longitude)
            : null;

        $check = LocationCheck::create([
            'package_id' => $package->id,
            'staff_id' => $courier?->id,
            'action' => $action,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_m' => $assessment->accuracyM,
            'level' => $assessment->level,
            'reasons' => $assessment->reasons,
            'distance_m' => $geo['distance_m'],
            'radius_m' => $geo['radius_m'],
            'inside_geofence' => $geo['inside'],
            'applied' => ! $blocked,
            'override_id' => $override?->id,
        ]);

        if ($blocked && $assessment->blocked()) {
            throw FakeGpsSuspected::fromAssessment($assessment, $action, $lockRequest?->id);
        }

        if ($blocked) {
            throw new OutsideGeofence(
                distanceM: $geo['distance_m'] ?? 0,
                radiusM: $geo['radius_m'],
                action: $action,
                lockRequestId: $lockRequest?->id,
            );
        }

        return $check;
    }

    /**
     * A courier asking the hub to review the last verdict for an action. Called
     * from the app after a block, and by the validator when it blocks.
     */
    public function requestReview(Package $package, ?Staff $courier, GpsAction $action): GpsLockRequest
    {
        $latest = LocationCheck::query()
            ->where('package_id', $package->id)
            ->where('action', $action)
            ->orderByDesc('id')
            ->first();

        $assessment = new GpsAssessment(
            level: $latest?->level ?? GpsLevel::SUSPECTED,
            reasons: is_array($latest?->reasons) ? $latest->reasons : [],
            accuracyM: $latest?->accuracy_m,
        );

        $geo = [
            'distance_m' => $latest?->distance_m,
            'radius_m' => $latest?->radius_m ?? $this->geofence->radiusFor($package->service_type),
            'inside' => $latest?->inside_geofence,
        ];

        return $this->openLockRequest(
            $package,
            $courier,
            $action,
            $assessment,
            $geo,
            $latest?->latitude,
            $latest?->longitude,
        );
    }

    public function latestCheck(Package $package, GpsAction $action): ?LocationCheck
    {
        return LocationCheck::query()
            ->where('package_id', $package->id)
            ->where('action', $action)
            ->orderByDesc('id')
            ->first();
    }

    public function approvedOverride(Package $package, GpsAction $action): ?GpsLockRequest
    {
        return GpsLockRequest::query()
            ->where('package_id', $package->id)
            ->where('action', $action)
            ->where('status', GpsLockStatus::APPROVED)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array{distance_m: int|null, radius_m: int, inside: bool|null}  $geo
     */
    private function openLockRequest(
        Package $package,
        ?Staff $courier,
        GpsAction $action,
        GpsAssessment $assessment,
        array $geo,
        ?float $latitude,
        ?float $longitude,
    ): GpsLockRequest {
        $existing = GpsLockRequest::query()
            ->where('package_id', $package->id)
            ->where('action', $action)
            ->where('status', GpsLockStatus::PENDING)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $reasons = $assessment->reasons;

        if (($geo['inside'] ?? null) === false) {
            $reasons[] = [
                'code' => 'outside_geofence',
                'label' => 'Di luar radius titik pengantaran',
                'detail' => 'Jarak '.($geo['distance_m'] ?? 0).' m dari titik penerima, radius yang diizinkan '.$geo['radius_m'].' m.',
                'severity' => 'strong',
            ];
        }

        return GpsLockRequest::create([
            'package_id' => $package->id,
            'staff_id' => $courier?->id,
            'action' => $action,
            'level' => GpsLevel::BLOCKED,
            'reasons' => $reasons,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => GpsLockStatus::PENDING,
        ]);
    }
}
