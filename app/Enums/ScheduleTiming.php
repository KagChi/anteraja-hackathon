<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

enum ScheduleTiming: string
{
    case ON_TIME = 'ON_TIME';
    case URGENT = 'URGENT';
    case LATE = 'LATE';

    public static function forWindow(?CarbonInterface $endAt): self
    {
        if ($endAt === null) {
            return self::ON_TIME;
        }

        $now = Carbon::now();

        if ($now->greaterThan($endAt)) {
            return self::LATE;
        }

        $diffMinutes = $now->diffInMinutes($endAt, false);
        if ($diffMinutes <= 30) {
            return self::URGENT;
        }

        return self::ON_TIME;
    }

    public function label(): string
    {
        return match ($this) {
            self::ON_TIME => 'Tepat Waktu',
            self::URGENT => 'Segera',
            self::LATE => 'Terlambat',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ON_TIME => 'scheduled',
            self::URGENT => 'warning',
            self::LATE => 'alert',
        };
    }
}
