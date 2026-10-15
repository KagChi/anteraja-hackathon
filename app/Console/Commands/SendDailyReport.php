<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reporting\DailyReportSender;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class SendDailyReport extends Command
{
    protected $signature = 'report:send-daily {--date= : Tanggal laporan (YYYY-MM-DD, zona WIB)}';

    protected $description = 'Kirim ringkasan harian operasi ke admin lewat WhatsApp';

    public function handle(DailyReportSender $sender): int
    {
        $date = $this->option('date');

        $day = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
            ? Carbon::createFromFormat('Y-m-d', $date, DisplayTime::DISPLAY_TIMEZONE)->startOfDay()
            : null;

        $summary = $sender->send($day);

        $this->info(sprintf(
            'Laporan %s dikirim ke %d admin.',
            $summary['report_day'],
            $summary['queued']
        ));

        return self::SUCCESS;
    }
}
