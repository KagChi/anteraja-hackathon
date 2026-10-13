<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexReportRequest;
use App\Models\IssueReport;
use App\Support\Csv\StreamedCsv;
use App\Support\DisplayTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportController extends Controller
{
    public function __invoke(IndexReportRequest $request): StreamedResponse
    {
        $filters = $request->toFilters();
        [$startUtc, $endUtc] = DisplayTime::dayRange($filters->date);

        $query = IssueReport::query()
            ->with(['check.package.buyer', 'check.courier', 'decidedByStaff'])
            ->where('reported_at', '>=', $startUtc)
            ->where('reported_at', '<', $endUtc);

        if ($filters->category !== null) {
            $query->where('issue_category', $filters->category);
        }

        $issues = $query->orderBy('reported_at', 'desc')->get();

        $headers = [
            'RESI',
            'PEMBELI',
            'KURIR',
            'JENIS_KENDALA',
            'KEPUTUSAN',
            'STATUS_RESOLUSI',
            'DILAPORKAN',
        ];

        $rows = $issues->map(function (IssueReport $issue): array {
            $pkg = $issue->check?->package;
            $catLabel = $issue->issue_category?->label() ?? '';
            if ($issue->issue_category?->value === 'suhu_naik' && $issue->check?->temperature_c !== null) {
                $catLabel .= " ({$issue->check->temperature_c}°C)";
            }

            return [
                $pkg?->tracking_number ?? '',
                $pkg?->buyer?->name ?? '',
                $issue->check?->courier?->name ?? '',
                $catLabel,
                $issue->decision?->label() ?? 'Menunggu Keputusan',
                $issue->resolutionStatus($pkg)->value,
                DisplayTime::toDisplay($issue->reported_at)?->format('Y-m-d H:i') ?? '',
            ];
        });

        return StreamedCsv::download("laporan-harian-{$filters->date}.csv", $headers, $rows);
    }
}
