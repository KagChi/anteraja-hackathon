<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexHistoryRequest;
use App\Models\Package;
use App\Queries\HistoryQuery;
use App\Support\Csv\StreamedCsv;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HistoryExportController extends Controller
{
    public function __invoke(IndexHistoryRequest $request, HistoryQuery $query): StreamedResponse
    {
        $packages = $query->get($request->toFilters());

        $headers = [
            'RESI',
            'PEMBELI',
            'KURIR',
            'SELESAI',
            'STATUS',
        ];

        $rows = $packages->map(function (Package $pkg): array {
            $completedAt = $pkg->completed_at !== null ? Carbon::parse($pkg->completed_at) : null;
            $completedLabel = '-';
            if ($completedAt !== null) {
                $completedLocal = DisplayTime::toDisplay($completedAt);
                $completedLabel = $completedLocal?->format('Y-m-d H:i').' WIB';
            }

            return [
                $pkg->tracking_number,
                $pkg->buyer?->name ?? '',
                $pkg->courier?->name ?? 'Belum Ditugaskan',
                $completedLabel,
                $pkg->statusRef?->label ?? $pkg->status->value,
            ];
        });

        $nowStr = Carbon::now('Asia/Jakarta')->format('Ymd-Hi');

        return StreamedCsv::download("riwayat-{$nowStr}.csv", $headers, $rows);
    }
}
