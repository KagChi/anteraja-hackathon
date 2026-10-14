<?php

declare(strict_types=1);

namespace App\Services\Gps;

use App\Enums\ServiceType;
use App\Models\Buyer;
use App\Models\GeofencePolicy;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side geofence evaluation (ported from the capstone's FRD-01, minus
 * PostGIS: destinations are plain lat/lng columns, so haversine does the work).
 *
 * The client never decides: the app shows the distance it can compute, and the
 * server re-derives it here for the verdict.
 */
final class GeofenceEvaluator
{
    public const CACHE_KEY = 'geofence.radii';

    public const CACHE_TTL_SECONDS = 60;

    /**
     * @return array{distance_m: int|null, radius_m: int, inside: bool|null}
     */
    public function evaluate(?Buyer $buyer, float $latitude, float $longitude, ?ServiceType $serviceType): array
    {
        $radius = $this->radiusFor($serviceType);

        if ($buyer === null || $buyer->latitude === null || $buyer->longitude === null) {
            // No destination coordinates on file: there is nothing to be inside
            // or outside of, so the geofence stays silent rather than failing.
            return ['distance_m' => null, 'radius_m' => $radius, 'inside' => null];
        }

        $distanceM = (int) round(GeoDistance::haversine(
            $latitude,
            $longitude,
            (float) $buyer->latitude,
            (float) $buyer->longitude,
        ) * 1000);

        return [
            'distance_m' => $distanceM,
            'radius_m' => $radius,
            'inside' => $distanceM <= $radius,
        ];
    }

    public function radiusFor(?ServiceType $serviceType): int
    {
        $key = $serviceType?->value ?? ServiceType::REGULER->value;
        $radii = $this->radii();

        return $radii[$key] ?? GeofencePolicy::DEFAULT_RADIUS_M[$key] ?? 100;
    }

    /**
     * @return array<string, int>
     */
    public function radii(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static fn (): array => GeofencePolicy::query()
                ->pluck('radius_m', 'service_type')
                ->map(static fn (mixed $radius): int => (int) $radius)
                ->all(),
        );
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
