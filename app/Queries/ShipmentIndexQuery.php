<?php

declare(strict_types=1);

namespace App\Queries;

use App\DTOs\ShipmentFilters;
use App\Models\Package;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ShipmentIndexQuery
{
    /**
     * @return Builder<Package>
     */
    public function query(ShipmentFilters $filters): Builder
    {
        $query = Package::query()
            ->select('packages.*')
            ->join('buyers', 'buyers.id', '=', 'packages.buyer_id')
            ->leftJoin('staff as couriers', 'couriers.id', '=', 'packages.courier_id')
            ->whereNotIn('packages.status', ['CLOSED', 'RETURNED'])
            ->with(['statusRef', 'buyer', 'courier', 'latestIssue']);

        // 1. Schedule Filter — an explicit date names one delivery day and wins
        // over the relative window, so a Laporan drill-down never gets the
        // window ANDed on top of it (which would return nothing for any day
        // other than today).
        $todayJkt = Carbon::now(DisplayTime::DISPLAY_TIMEZONE);
        if ($filters->date !== null) {
            [$startUtc, $endUtc] = DisplayTime::dayRange($filters->date);
            $query->where('packages.scheduled_at', '>=', $startUtc)
                ->where('packages.scheduled_at', '<', $endUtc);
        } elseif ($filters->schedule === 'today') {
            [$startUtc, $endUtc] = DisplayTime::dayRange($todayJkt);
            $query->where('packages.scheduled_at', '>=', $startUtc)
                ->where('packages.scheduled_at', '<', $endUtc);
        } elseif ($filters->schedule === 'tomorrow') {
            [$startUtc, $endUtc] = DisplayTime::dayRange($todayJkt->copy()->addDay());
            $query->where('packages.scheduled_at', '>=', $startUtc)
                ->where('packages.scheduled_at', '<', $endUtc);
        } elseif ($filters->schedule === 'upcoming') {
            [$startUtc] = DisplayTime::dayRange($todayJkt);
            $query->where('packages.scheduled_at', '>=', $startUtc);
        }

        // 2. Status Filter
        if ($filters->status !== null) {
            $query->where('packages.status', $filters->status);
        }

        // 2b. Origin Filter - orders the customer app opened are tagged
        //     CLIENT_APP so the hub can work the app queue on its own.
        if ($filters->source !== null) {
            $query->where('packages.source', $filters->source);
        }

        // 3. Courier Filter
        if ($filters->courier === 'unassigned') {
            $query->whereNull('packages.courier_id');
        } elseif ($filters->courier !== null && is_numeric($filters->courier)) {
            $query->where('packages.courier_id', (int) $filters->courier);
        }

        // 4. Search Filter (escaped ILIKE)
        if ($filters->q !== null) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->q);
            $term = "%{$escaped}%";

            $query->where(function (Builder $q) use ($term): void {
                $q->where('packages.tracking_number', 'ILIKE', $term)
                    ->orWhere('buyers.name', 'ILIKE', $term)
                    ->orWhere('buyers.area', 'ILIKE', $term)
                    ->orWhere('couriers.name', 'ILIKE', $term);
            });
        }

        // Deterministic sort
        return $query->orderBy('packages.tracking_number', 'asc')
            ->orderBy('packages.id', 'asc');
    }

    public function paginate(ShipmentFilters $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters->perPage)->withQueryString();
    }

    /**
     * @return Collection<int, Package>
     */
    public function get(ShipmentFilters $filters): Collection
    {
        return $this->query($filters)->get();
    }
}
