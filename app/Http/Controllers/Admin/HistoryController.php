<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexHistoryRequest;
use App\Models\Package;
use App\Queries\HistoryQuery;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class HistoryController extends Controller
{
    public function index(IndexHistoryRequest $request, HistoryQuery $query): JsonResponse
    {
        $filters = $request->toFilters();
        $paginated = $query->paginate($filters, 15);

        $transformedRows = collect($paginated->items())->map(function (Package $pkg) {
            $completedAt = $pkg->completed_at !== null ? Carbon::parse($pkg->completed_at) : null;
            $completedLabel = '-';
            if ($completedAt !== null) {
                $completedLocal = DisplayTime::toDisplay($completedAt);
                $datePrefix = DisplayTime::label($completedAt);
                $completedLabel = "{$datePrefix}, ".($completedLocal?->format('H:i') ?? '').' WIB';
            }

            return [
                'id' => $pkg->id,
                'tracking' => $pkg->tracking_number,
                'buyer' => $pkg->buyer?->name ?? '-',
                'courier' => $pkg->courier?->name ?? 'Belum Ditugaskan',
                'completed_at' => $completedLabel,
                'status' => $pkg->statusRef?->label ?? $pkg->status->value,
                'statusKey' => strtolower($pkg->status->value),
                'tone' => $pkg->status->tone()->value,
            ];
        });

        $data = [
            'history' => $transformedRows,
            'filters' => [
                'q' => $filters->q,
                'status' => $filters->status,
                'page' => $filters->page,
            ],
            'paginator' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];

        return response()->json($data);
    }
}
