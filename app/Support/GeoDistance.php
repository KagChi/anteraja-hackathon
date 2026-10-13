<?php

declare(strict_types=1);

namespace App\Support;

final class GeoDistance
{
    public const EARTH_RADIUS_KM = 6371.0;

    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return round($angle * self::EARTH_RADIUS_KM, 1);
    }

    public static function etaMinutes(float $distanceKm, ?float $avgSpeedKmh = null): int
    {
        $speed = $avgSpeedKmh ?? (float) config('coldchain.courier_avg_speed_kmh', 12.0);
        if ($speed <= 0) {
            $speed = 12.0;
        }

        $minutes = (int) ceil(($distanceKm / $speed) * 60);

        return max(1, $minutes);
    }
}
