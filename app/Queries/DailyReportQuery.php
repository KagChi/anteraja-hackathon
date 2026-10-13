<?php

declare(strict_types=1);

namespace App\Queries;

use App\DTOs\ReportFilters;
use App\Models\IssueReport;
use App\Support\DisplayTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DailyReportQuery
{
    /**
     * @return array{
     *     total_shipments: int,
     *     fleet_capacity_pct: float,
     *     delivered_count: int,
     *     success_rate_pct: float,
     *     issue_count: int,
     *     incident_rate_pct: float
     * }
     */
    public function getKpis(ReportFilters $filters): array
    {
        [$startUtc, $endUtc] = DisplayTime::dayRange($filters->date);

        $kpi = DB::selectOne(
            <<<'SQL'
SELECT
    (SELECT COUNT(*) FROM packages WHERE scheduled_at >= ? AND scheduled_at < ?) AS total_shipments,
    (SELECT COUNT(*) FROM packages p
     JOIN deliveries d ON d.package_id = p.id
     WHERE p.scheduled_at >= ? AND p.scheduled_at < ?) AS delivered_count,
    (SELECT COUNT(*) FROM issue_reports WHERE reported_at >= ? AND reported_at < ?) AS issue_count
SQL,
            [
                $startUtc, $endUtc,
                $startUtc, $endUtc,
                $startUtc, $endUtc,
            ]
        );

        $total = (int) ($kpi->total_shipments ?? 0);
        $delivered = (int) ($kpi->delivered_count ?? 0);
        $issues = (int) ($kpi->issue_count ?? 0);

        $fleetCapacity = (int) config('coldchain.daily_fleet_capacity', 1500);
        $fleetCapacityPct = $fleetCapacity > 0 ? round(($total / $fleetCapacity) * 100, 1) : 0.0;
        $successRatePct = $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
        $incidentRatePct = $total > 0 ? round(($issues / $total) * 100, 2) : 0.0;

        return [
            'total_shipments' => $total,
            'fleet_capacity_pct' => $fleetCapacityPct,
            'delivered_count' => $delivered,
            'success_rate_pct' => $successRatePct,
            'issue_count' => $issues,
            'incident_rate_pct' => $incidentRatePct,
        ];
    }

    public function paginateIssues(ReportFilters $filters, int $perPage = 5): LengthAwarePaginator
    {
        [$startUtc, $endUtc] = DisplayTime::dayRange($filters->date);

        $query = IssueReport::query()
            ->with([
                'check.package.buyer',
                'check.package.statusRef',
                'check.courier',
                'decidedByStaff',
            ])
            ->where('reported_at', '>=', $startUtc)
            ->where('reported_at', '<', $endUtc);

        if ($filters->category !== null) {
            $query->where('issue_category', $filters->category);
        }

        return $query->orderBy('reported_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $filters->page)
            ->withQueryString();
    }
}
