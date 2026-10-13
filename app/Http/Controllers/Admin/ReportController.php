<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexReportRequest;
use App\Models\IssueReport;
use App\Queries\DailyReportQuery;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class ReportController extends Controller
{
    public function index(IndexReportRequest $request, DailyReportQuery $query): JsonResponse
    {
        $filters = $request->toFilters();

        $todayStr = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->format('Y-m-d');
        $isPastDate = $filters->date < $todayStr;

        if ($isPastDate) {
            $kpis = Cache::remember("report_kpis_{$filters->date}", 600, fn () => $query->getKpis($filters));
        } else {
            $kpis = $query->getKpis($filters);
        }

        $issuesPaginated = $query->paginateIssues($filters, 5);

        // Fetch categories present on this date
        [$startUtc, $endUtc] = DisplayTime::dayRange($filters->date);
        $categoriesOnDate = IssueReport::where('reported_at', '>=', $startUtc)
            ->where('reported_at', '<', $endUtc)
            ->distinct()
            ->pluck('issue_category')
            ->filter()
            ->values()
            ->all();

        $dateCarbon = Carbon::createFromFormat('Y-m-d', $filters->date, DisplayTime::DISPLAY_TIMEZONE);
        $dateLabel = $dateCarbon !== false ? $dateCarbon->locale('id')->translatedFormat('j F Y') : $filters->date;

        $transformedIssues = collect($issuesPaginated->items())->map(function (IssueReport $issue) {
            $pkg = $issue->check?->package;
            $cat = $issue->issue_category;
            $catLabel = $cat?->label() ?? 'Kendala';
            if ($cat?->value === 'suhu_naik' && $issue->check?->temperature_c !== null) {
                $catLabel .= " ({$issue->check->temperature_c}°C)";
            }

            return [
                'id' => $issue->id,
                'tracking' => $pkg?->tracking_number ?? '-',
                'buyer' => $pkg?->buyer?->name ?? '-',
                'courier' => $issue->check?->courier?->name ?? '-',
                'category' => $catLabel,
                'decision' => $issue->decision?->label() ?? 'Menunggu Keputusan',
                'resolutionStatus' => $issue->resolutionStatus($pkg)->value,
                'reported_at' => DisplayTime::toDisplay($issue->reported_at)?->format('H:i').' WIB',
            ];
        });

        $data = [
            'date' => $filters->date,
            'dateLabel' => $dateLabel,
            'kpis' => $kpis,
            'issues' => $transformedIssues,
            'categoriesOnDate' => $categoriesOnDate,
            'selectedCategory' => $filters->category,
            'paginator' => [
                'total' => $issuesPaginated->total(),
                'per_page' => $issuesPaginated->perPage(),
                'current_page' => $issuesPaginated->currentPage(),
                'last_page' => $issuesPaginated->lastPage(),
            ],
        ];

        return response()->json($data);
    }
}
