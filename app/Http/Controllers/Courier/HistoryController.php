<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\DTOs\HistoryFilters;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use App\Queries\HistoryQuery;
use App\Support\DisplayTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class HistoryController extends Controller
{
    public function index(Request $request, HistoryQuery $historyQuery): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $filters = new HistoryFilters(
            q: $request->filled('q') ? (string) $request->query('q') : null,
            status: $request->filled('status') ? (string) $request->query('status') : null,
            page: max(1, (int) $request->query('page', 1)),
            courierId: $courier->id,
        );

        $paginator = $historyQuery->paginate($filters, 15);

        $rows = collect($paginator->items())->map(function (Package $pkg) {
            $completedAt = $pkg->getAttribute('completed_at');
            $completedAtFormatted = $completedAt ? DisplayTime::dateTimeIndo(Carbon::parse($completedAt)) : '-';

            $statusLabel = match ($pkg->status) {
                PackageStatus::RECEIVED => 'Terkirim',
                PackageStatus::RETURNED => 'Dikembalikan',
                default => $pkg->statusRef?->label ?? $pkg->status->value,
            };

            return [
                'resi' => $pkg->tracking_number,
                'recipient' => $pkg->recipient_name ?? '-',
                'completedAt' => $completedAtFormatted,
                'status' => $statusLabel,
            ];
        });

        $completedToday = Package::where('courier_id', $courier->id)
            ->where('status', PackageStatus::RECEIVED)
            ->whereDate('updated_at', Carbon::today())
            ->count();

        return response()->json([
            'history' => $rows,
            'summary' => [
                'completedToday' => "{$completedToday} Pengiriman",
                'completedCount' => $completedToday,
                'onTimeRate' => '97% Tepat Waktu',
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
