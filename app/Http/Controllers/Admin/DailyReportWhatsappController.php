<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DailyReportSender;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tombol "kirim sekarang" di halaman Laporan: admin bisa memicu laporan
 * harian kapan saja, termasuk saat demo, tanpa menunggu jadwal harian.
 */
final class DailyReportWhatsappController extends Controller
{
    public function __invoke(Request $request, DailyReportSender $sender): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $day = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', (string) $validated['date'], DisplayTime::DISPLAY_TIMEZONE)->startOfDay()
            : null;

        $summary = $sender->send($day);
        $queued = (int) $summary['queued'];
        unset($summary['queued']);

        return response()->json([
            'queued' => $queued,
            'summary' => $summary,
            'message' => $queued > 0
                ? "Laporan harian dikirim ke {$queued} admin."
                : 'Tidak ada admin aktif yang bisa dikirimi laporan.',
        ]);
    }
}
