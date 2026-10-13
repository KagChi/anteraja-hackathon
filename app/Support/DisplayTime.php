<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DisplayTime
{
    public const string DISPLAY_TIMEZONE = 'Asia/Jakarta';

    public static function toDisplay(?CarbonInterface $time): ?Carbon
    {
        if ($time === null) {
            return null;
        }

        return Carbon::instance($time)->setTimezone(self::DISPLAY_TIMEZONE);
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [startUtc, endUtc] half-open: >= start AND < end
     */
    public static function dayRange(string|CarbonInterface $date): array
    {
        if (is_string($date)) {
            $parsed = Carbon::createFromFormat('Y-m-d', $date, self::DISPLAY_TIMEZONE);
            $local = $parsed !== false ? $parsed->startOfDay() : Carbon::now(self::DISPLAY_TIMEZONE)->startOfDay();
        } else {
            $local = Carbon::instance($date)->setTimezone(self::DISPLAY_TIMEZONE)->startOfDay();
        }

        $startUtc = $local->copy()->setTimezone('UTC');
        $endUtc = $local->copy()->addDay()->setTimezone('UTC');

        return [$startUtc, $endUtc];
    }

    public static function label(?CarbonInterface $time): string
    {
        if ($time === null) {
            return '-';
        }

        $local = self::toDisplay($time);
        if ($local === null) {
            return '-';
        }

        $today = Carbon::now(self::DISPLAY_TIMEZONE)->startOfDay();
        $targetDate = $local->copy()->startOfDay();

        if ($targetDate->equalTo($today)) {
            return 'Hari ini';
        }

        if ($targetDate->equalTo($today->copy()->addDay())) {
            return 'Besok';
        }

        if ($targetDate->equalTo($today->copy()->subDay())) {
            return 'Kemarin';
        }

        return $local->locale('id')->translatedFormat('j M');
    }

    public static function windowLabel(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        if ($start === null) {
            return '-';
        }

        $startLocal = self::toDisplay($start);
        $prefix = self::label($start);

        if ($end !== null) {
            $endLocal = self::toDisplay($end);

            return sprintf(
                '%s, %s - %s',
                $prefix,
                $startLocal?->format('H:i') ?? '',
                $endLocal?->format('H:i') ?? ''
            );
        }

        return sprintf('%s, %s', $prefix, $startLocal?->format('H:i') ?? '');
    }

    public static function dateTimeIndo(?CarbonInterface $time): string
    {
        if ($time === null) {
            return '-';
        }

        $local = self::toDisplay($time);
        if ($local === null) {
            return '-';
        }

        $datePrefix = self::label($local);
        $timeStr = $local->format('H:i').' WIB';

        return "{$datePrefix} • {$timeStr}";
    }
}
