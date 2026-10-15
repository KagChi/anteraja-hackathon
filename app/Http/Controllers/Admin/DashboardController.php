<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\ReportFilters;
use App\Http\Controllers\Controller;
use App\Models\StatusLog;
use App\Queries\DashboardQuery;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Beranda admin: the day's auto summary and its audit trail.
 *
 * Past days are cached the same way the Laporan screen caches its KPIs, while
 * today is always computed live so a fresh status change shows up on the next
 * auto refresh.
 */
final class DashboardController extends Controller
{
    public function index(Request $request, DashboardQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = ReportFilters::fromArray($validated);
        $date = $filters->date;
        $todayStr = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->format('Y-m-d');

        $summary = $date < $todayStr
            ? Cache::remember("dashboard_summary_{$date}", 600, fn () => $query->summary($date))
            : $query->summary($date);

        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date, DisplayTime::DISPLAY_TIMEZONE);
        $dateLabel = $dateCarbon !== false ? $dateCarbon->locale('id')->translatedFormat('j F Y') : $date;

        $activity = $query->paginateActivity($date, $filters->page, 15, $validated['q'] ?? null);

        return response()->json([
            'date' => $date,
            'dateLabel' => $dateLabel,
            'isToday' => $date === $todayStr,
            'summary' => $summary,
            'narrative' => $query->narrative($summary, $dateLabel),
            'activity' => collect($activity->items())
                ->map(fn (StatusLog $log) => $query->presentActivity($log))
                ->all(),
            'paginator' => [
                'total' => $activity->total(),
                'per_page' => $activity->perPage(),
                'current_page' => $activity->currentPage(),
                'last_page' => $activity->lastPage(),
            ],
            'refreshedAt' => Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->format('H:i').' WIB',
        ]);
    }
}
