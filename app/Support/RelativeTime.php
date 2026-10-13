<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class RelativeTime
{
    public static function diffForHumansId(?CarbonInterface $time): string
    {
        if ($time === null) {
            return '-';
        }

        $now = Carbon::now();
        $diffSeconds = $now->diffInSeconds($time, true);

        if ($diffSeconds < 60) {
            return 'baru saja';
        }

        $diffMinutes = (int) floor($diffSeconds / 60);
        if ($diffMinutes < 60) {
            return "Masuk {$diffMinutes} mnt lalu";
        }

        $diffHours = (int) floor($diffMinutes / 60);
        if ($diffHours < 24) {
            return "Masuk {$diffHours} jam lalu";
        }

        $diffDays = (int) floor($diffHours / 24);
        if ($diffDays === 1) {
            return 'Kemarin';
        }

        return "{$diffDays} hari lalu";
    }
}
