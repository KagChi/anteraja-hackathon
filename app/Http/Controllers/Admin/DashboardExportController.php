<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\ReportFilters;
use App\Http\Controllers\Controller;
use App\Models\StatusLog;
use App\Queries\DashboardQuery;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The beranda's "Ekspor PDF": a branded A4 recap of one day's summary and
 * audit trail, rendered from a Blade view through dompdf.
 */
final class DashboardExportController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $query): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        $date = ReportFilters::fromArray($validated)->date;

        $summary = $query->summary($date);

        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date, DisplayTime::DISPLAY_TIMEZONE);
        $dateLabel = $dateCarbon !== false ? $dateCarbon->locale('id')->translatedFormat('j F Y') : $date;

        $activity = $query->paginateActivity($date, 1, 100);

        $html = view('admin.dashboard-report', [
            'date' => $date,
            'dateLabel' => $dateLabel,
            'summary' => $summary,
            'narrative' => $query->narrative($summary, $dateLabel),
            'activity' => collect($activity->items())
                ->map(fn (StatusLog $log) => $query->presentActivity($log))
                ->all(),
            'generatedAt' => Carbon::now(DisplayTime::DISPLAY_TIMEZONE)
                ->locale('id')
                ->translatedFormat('j F Y, H:i').' WIB',
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('dpi', 120);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-operasi-'.$date.'.pdf"',
        ]);
    }
}
