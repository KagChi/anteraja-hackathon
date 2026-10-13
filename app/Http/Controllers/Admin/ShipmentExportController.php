<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexShipmentRequest;
use App\Models\Package;
use App\Queries\ShipmentIndexQuery;
use App\Support\Csv\StreamedCsv;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShipmentExportController extends Controller
{
    public function __invoke(IndexShipmentRequest $request, ShipmentIndexQuery $query): StreamedResponse
    {
        $packages = $query->get($request->toFilters());

        $headers = [
            'RESI',
            'PEMBELI',
            'AREA',
            'JADWAL',
            'KURIR',
            'STATUS',
            'BERAT_KG',
        ];

        $rows = $packages->map(function (Package $pkg): array {
            return [
                $pkg->tracking_number,
                $pkg->buyer?->name ?? '',
                $pkg->buyer?->area ?? $pkg->buyer?->address ?? '',
                DisplayTime::windowLabel($pkg->scheduled_at, $pkg->scheduled_end_at),
                $pkg->courier?->name ?? 'Belum Ditugaskan',
                $pkg->statusRef?->label ?? $pkg->status->value,
                (string) $pkg->weight_kg,
            ];
        });

        $nowStr = Carbon::now('Asia/Jakarta')->format('Ymd-Hi');

        return StreamedCsv::download("shipment-{$nowStr}.csv", $headers, $rows);
    }
}
