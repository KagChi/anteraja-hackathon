<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PackageStatus;
use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ClientOrderListQuery
{
    /**
     * @return array{
     *     paginator: LengthAwarePaginator,
     *     counts: array{active: int, completed: int, issue: int, total: int}
     * }
     */
    public function execute(
        int $buyerId,
        string $tab = 'active',
        ?string $search = null,
        int $perPage = 10,
        int $page = 1
    ): array {
        // 1. Single aggregate query for tab counts
        $countsRow = DB::table('packages')
            ->where('buyer_id', $buyerId)
            ->selectRaw("
                COUNT(*) FILTER (WHERE status NOT IN ('RECEIVED', 'RETURNED', 'CLOSED')) AS active_count,
                COUNT(*) FILTER (WHERE status IN ('RECEIVED', 'CLOSED')) AS completed_count,
                COUNT(*) FILTER (WHERE status IN ('ISSUE_REPORTED', 'RETURNED')) AS issue_count,
                COUNT(*) AS total_count
            ")
            ->first();

        $counts = [
            'active' => (int) ($countsRow->active_count ?? 0),
            'completed' => (int) ($countsRow->completed_count ?? 0),
            'issue' => (int) ($countsRow->issue_count ?? 0),
            'total' => (int) ($countsRow->total_count ?? 0),
        ];

        // 2. Build list query with eager loading to prevent N+1
        $query = Package::query()
            ->where('buyer_id', $buyerId)
            ->with([
                'items.product',
                'buyer',
                'courier',
                'delivery',
                'scheduleConfirmations',
                'conditionChecks.issueReport',
            ]);

        // Filter tab
        match ($tab) {
            'completed' => $query->whereIn('status', [PackageStatus::RECEIVED->value, PackageStatus::CLOSED->value]),
            'issue' => $query->whereIn('status', [PackageStatus::ISSUE_REPORTED->value, PackageStatus::RETURNED->value]),
            'all' => $query,
            default => $query->whereNotIn('status', [PackageStatus::RECEIVED->value, PackageStatus::RETURNED->value, PackageStatus::CLOSED->value]),
        };

        // Search query
        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('tracking_number', 'ILIKE', $term)
                    ->orWhereHas('items.product', function ($pq) use ($term) {
                        $pq->where('name', 'ILIKE', $term);
                    });
            });
        }

        // Order by scheduled time / created time
        $paginator = $query
            ->orderByRaw('scheduled_at DESC NULLS LAST')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'paginator' => $paginator,
            'counts' => $counts,
        ];
    }
}
