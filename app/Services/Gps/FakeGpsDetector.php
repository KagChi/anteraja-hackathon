<?php

declare(strict_types=1);

namespace App\Services\Gps;

use App\DTOs\Courier\GpsAssessment;
use App\DTOs\Courier\GpsFixData;
use App\DTOs\Courier\GpsSignalData;
use App\Enums\GpsLevel;
use App\Models\LocationCheck;
use App\Models\Package;
use App\Support\GeoDistance;
use Carbon\Carbon;

/**
 * Fix-window heuristics ported from the capstone's FRD-06.
 *
 * The courier app ships the raw signals it can see (accuracy, device clock, the
 * recent fix window); the server decides. Strong signals block an action until
 * the hub reviews it, weak signals only flag the attempt.
 *
 * Thresholds mirror the capstone detector so both prototypes agree on what
 * "suspicious" means.
 */
final class FakeGpsDetector
{
    // Strong signals: the action is refused.
    public const ACCURACY_MIN_M = 1.0;

    public const FROZEN_MIN_FIXES = 8;

    public const FROZEN_MIN_SPAN_SECONDS = 45;

    public const FROZEN_MAX_ACCURACY_M = 2.0;

    public const TRAVEL_MAX_SPEED_KMH = 200.0;

    public const TRAVEL_MIN_GAP_SECONDS = 60;

    public const TRAVEL_LOOKBACK_SECONDS = 900;

    // Weak signals: flagged, still allowed.
    public const CLOCK_SKEW_SECONDS = 900;

    public const STALE_FIX_SECONDS = 600;

    public const TELEPORT_MAX_SPEED_KMH = 150.0;

    public const TELEPORT_MIN_GAP_SECONDS = 5;

    public const TELEPORT_MIN_DISTANCE_M = 200;

    public const DIVERSITY_MIN_SPAN_SECONDS = 45;

    public const DIVERSITY_MIN_DISTINCT_POINTS = 3;

    public const DIVERSITY_MAX_ACCURACY_M = 5.0;

    public const MAX_FIX_WINDOW = 60;

    /** @var list<string> */
    public const STRONG_CODES = ['accuracy_invalid', 'frozen_fix', 'impossible_travel'];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'accuracy_invalid' => 'Akurasi GPS tidak wajar',
        'frozen_fix' => 'Koordinat beku (tidak ada variasi sinyal)',
        'impossible_travel' => 'Perpindahan mustahil antar titik tercatat',
        'clock_skew' => 'Jam perangkat berbeda dari server',
        'stale_fix' => 'Data GPS sudah lama',
        'client_teleport' => 'Lompatan posisi tidak wajar',
        'low_diversity' => 'Variasi sinyal GPS rendah',
    ];

    public function assess(Package $package, GpsSignalData $signals, ?Carbon $now = null): GpsAssessment
    {
        if ($signals->isEmpty()) {
            return GpsAssessment::clean();
        }

        $now ??= Carbon::now('UTC');
        $fixes = $signals->fixes;
        $latest = $signals->latest();

        if ($latest === null) {
            return GpsAssessment::clean();
        }

        $reasons = [];

        // 1. Mock-provider fingerprint: real hardware never reports sub-metre
        //    accuracy, and simulated locations usually report exactly zero.
        if ($latest->accuracy !== null && $latest->accuracy < self::ACCURACY_MIN_M) {
            $reasons[] = $this->reason(
                'accuracy_invalid',
                'strong',
                'Perangkat melaporkan akurasi sekitar '.$this->number($latest->accuracy).' m (ciri lokasi simulasi).',
            );
        }

        $window = $this->windowStats($fixes);

        // 2. Frozen feed: many fixes over a long span at the exact same point.
        $frozen = $window !== null
            && $window['count'] >= self::FROZEN_MIN_FIXES
            && $window['span_seconds'] >= self::FROZEN_MIN_SPAN_SECONDS
            && $window['distinct_points'] === 1
            && $latest->accuracy !== null
            && $latest->accuracy <= self::FROZEN_MAX_ACCURACY_M;

        if ($frozen && $window !== null) {
            $reasons[] = $this->reason(
                'frozen_fix',
                'strong',
                $window['count'].' titik identik selama '.$window['span_seconds'].' dtk tanpa variasi sinyal GPS.',
            );
        }

        // 3. Teleporting inside the window. Weak: a fix can jump while the
        //    device reacquires satellites.
        $teleport = $this->teleport($fixes);
        if ($teleport !== null) {
            $reasons[] = $this->reason(
                'client_teleport',
                'weak',
                'Posisi melompat '.$this->number($teleport['distance_m'] / 1000).' km dalam '.$teleport['gap_seconds'].' dtk.',
            );
        }

        // 4. Stale window: the app is shipping coordinates it captured long ago.
        if ($latest->timestamp !== null) {
            $age = (int) floor(($now->valueOf() - $latest->timestamp) / 1000);
            if ($age > self::STALE_FIX_SECONDS) {
                $reasons[] = $this->reason('stale_fix', 'weak', 'Fix terakhir berumur '.$this->duration($age).'.');
            }
        }

        // 5. Device clock disagreeing with the server.
        if ($signals->deviceTimestamp !== null) {
            $skew = (int) floor(abs($now->valueOf() - $signals->deviceTimestamp) / 1000);
            if ($skew > self::CLOCK_SKEW_SECONDS) {
                $reasons[] = $this->reason('clock_skew', 'weak', 'Jam perangkat berbeda '.$this->duration($skew).' dari server.');
            }
        }

        // 6. Precise but static: high accuracy claims with almost no spread.
        if ($window !== null
            && $window['span_seconds'] >= self::DIVERSITY_MIN_SPAN_SECONDS
            && $window['distinct_points'] < self::DIVERSITY_MIN_DISTINCT_POINTS
            && $latest->accuracy !== null
            && $latest->accuracy <= self::DIVERSITY_MAX_ACCURACY_M
            && ! $frozen
        ) {
            $reasons[] = $this->reason(
                'low_diversity',
                'weak',
                'Hanya '.$window['distinct_points'].' titik berbeda dalam '.$window['span_seconds'].' dtk.',
            );
        }

        // 7. Server side: a teleport against the points this shipment already
        //    recorded, which the client cannot see or rewrite.
        $travel = $this->impossibleTravel($package, $latest, $now);
        if ($travel !== null) {
            $reasons[] = $this->reason(
                'impossible_travel',
                'strong',
                'Perpindahan '.$this->number($travel['distance_m'] / 1000).' km dalam '.$this->duration($travel['gap_seconds']).' ('.$this->number($travel['speed_kmh']).' km/j).',
            );
        }

        return new GpsAssessment(
            level: $this->level($reasons),
            reasons: $reasons,
            accuracyM: $latest->accuracy,
            fixWindow: $window,
            impliedSpeedKmh: $travel['speed_kmh'] ?? null,
        );
    }

    /**
     * @param  list<array{code: string, label: string, detail: string, severity: string}>  $reasons
     */
    private function level(array $reasons): GpsLevel
    {
        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'strong') {
                return GpsLevel::BLOCKED;
            }
        }

        return $reasons === [] ? GpsLevel::CLEAN : GpsLevel::SUSPECTED;
    }

    /**
     * @param  list<GpsFixData>  $fixes
     * @return array{count: int, span_seconds: int, distinct_points: int}|null
     */
    private function windowStats(array $fixes): ?array
    {
        if ($fixes === []) {
            return null;
        }

        $timestamps = [];
        $points = [];

        foreach ($fixes as $fix) {
            if ($fix->timestamp !== null) {
                $timestamps[] = $fix->timestamp;
            }

            $points[sprintf('%.6F,%.6F', $fix->latitude, $fix->longitude)] = true;
        }

        $span = 0;
        if (count($timestamps) >= 2) {
            $span = (int) max(0, round((max($timestamps) - min($timestamps)) / 1000));
        }

        return [
            'count' => count($fixes),
            'span_seconds' => $span,
            'distinct_points' => count($points),
        ];
    }

    /**
     * @param  list<GpsFixData>  $fixes
     * @return array{distance_m: int, gap_seconds: int}|null
     */
    private function teleport(array $fixes): ?array
    {
        for ($index = 1; $index < count($fixes); $index++) {
            $previous = $fixes[$index - 1];
            $current = $fixes[$index];

            if ($previous->timestamp === null || $current->timestamp === null) {
                continue;
            }

            $gap = (int) (($current->timestamp - $previous->timestamp) / 1000);
            if ($gap < self::TELEPORT_MIN_GAP_SECONDS) {
                continue;
            }

            $distance = $this->metersBetween($previous, $current);
            if ($distance < self::TELEPORT_MIN_DISTANCE_M) {
                continue;
            }

            $speed = ($distance / 1000) / ($gap / 3600);
            if ($speed > self::TELEPORT_MAX_SPEED_KMH) {
                return ['distance_m' => $distance, 'gap_seconds' => $gap];
            }
        }

        return null;
    }

    /**
     * Compares this fix against the points the server already recorded for the
     * shipment, which is evidence the client cannot edit.
     *
     * @return array{distance_m: int, gap_seconds: int, speed_kmh: float}|null
     */
    private function impossibleTravel(Package $package, GpsFixData $fix, Carbon $now): ?array
    {
        $since = $now->copy()->subSeconds(self::TRAVEL_LOOKBACK_SECONDS);

        /** @var iterable<int, LocationCheck> $previous */
        $previous = LocationCheck::query()
            ->where('package_id', $package->id)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        foreach ($previous as $check) {
            $recordedAt = $check->created_at;
            if ($recordedAt === null) {
                continue;
            }

            $gap = (int) $recordedAt->diffInSeconds($now, true);
            if ($gap < self::TRAVEL_MIN_GAP_SECONDS) {
                continue;
            }

            $distance = (int) round(GeoDistance::haversine(
                (float) $check->latitude,
                (float) $check->longitude,
                $fix->latitude,
                $fix->longitude,
            ) * 1000);

            $speed = ($distance / 1000) / ($gap / 3600);
            if ($speed > self::TRAVEL_MAX_SPEED_KMH) {
                return [
                    'distance_m' => $distance,
                    'gap_seconds' => $gap,
                    'speed_kmh' => round($speed, 1),
                ];
            }
        }

        return null;
    }

    private function metersBetween(GpsFixData $a, GpsFixData $b): int
    {
        return (int) round(GeoDistance::haversine($a->latitude, $a->longitude, $b->latitude, $b->longitude) * 1000);
    }

    /**
     * @return array{code: string, label: string, detail: string, severity: string}
     */
    private function reason(string $code, string $severity, string $detail): array
    {
        return [
            'code' => $code,
            'label' => self::LABELS[$code] ?? $code,
            'detail' => $detail,
            'severity' => $severity,
        ];
    }

    private function number(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' dtk';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes.' mnt';
        }

        return intdiv($minutes, 60).' jam';
    }
}
