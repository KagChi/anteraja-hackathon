<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('confirmations:remind-unanswered')->everyFifteenMinutes();

// Ringkasan harian dikirim pukul 20:00 WIB, setelah semua pengantaran hari itu
// selesai. Zona ditulis eksplisit karena penjadwal Laravel berjalan di UTC.
Schedule::command('report:send-daily')
    ->timezone(\App\Support\DisplayTime::DISPLAY_TIMEZONE)
    ->dailyAt('20:00');
