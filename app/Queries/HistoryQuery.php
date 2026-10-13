<?php

declare(strict_types=1);

namespace App\Queries;

use App\DTOs\HistoryFilters;
use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class HistoryQuery
{
    /**
     * @return Builder<Package>
     */
    public function query(HistoryFilters $filters): Builder
    {
        $query = Package::query()
            ->select('packages.*')
            ->selectRaw(
                "COALESCE(
                    deliveries.delivered_at,
                    (SELECT MAX(changed_at) FROM status_logs WHERE package_id = packages.id AND to_status = 'RETURNED')
                ) AS completed_at"
            )
            ->join('buyers', 'buyers.id', '=', 'packages.buyer_id')
            ->leftJoin('staff as couriers', 'couriers.id', '=', 'packages.courier_id')
            ->leftJoin('deliveries', 'deliveries.package_id', '=', 'packages.id')
            ->whereIn('packages.status', ['RECEIVED', 'RETURNED', 'CLOSED'])
            ->with(['statusRef', 'buyer', 'courier', 'delivery']);

        if ($filters->courierId !== null) {
            $query->where('packages.courier_id', $filters->courierId);
        }

        if ($filters->status !== null) {
            $query->where('packages.status', $filters->status);
        }

        if ($filters->q !== null) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->q);
            $term = "%{$escaped}%";

            $query->where(function (Builder $q) use ($term): void {
                $q->where('packages.tracking_number', 'ILIKE', $term)
                    ->orWhere('buyers.name', 'ILIKE', $term)
                    ->orWhere('couriers.name', 'ILIKE', $term);
            });
        }

        return $query->orderByRaw('completed_at DESC NULLS LAST')
            ->orderBy('packages.id', 'desc');
    }

    public function paginate(HistoryFilters $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage, ['*'], 'page', $filters->page)->withQueryString();
    }

    /**
     * @return Collection<int, Package>
     */
    public function get(HistoryFilters $filters): Collection
    {
        return $this->query($filters)->get();
    }
}
