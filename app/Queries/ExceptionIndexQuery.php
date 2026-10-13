<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\IssueReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ExceptionIndexQuery
{
    /**
     * @return Builder<IssueReport>
     */
    public function query(string $tab = 'active'): Builder
    {
        $query = IssueReport::query()
            ->with([
                'check.package.buyer',
                'check.package.statusRef',
                'check.courier',
                'decidedByStaff',
            ]);

        if ($tab === 'active') {
            $query->whereNull('issue_reports.decision')
                ->orderBy('issue_reports.reported_at', 'asc');
        } else {
            $query->whereNotNull('issue_reports.decision')
                ->orderBy('issue_reports.reported_at', 'desc');
        }

        return $query;
    }

    public function paginate(string $tab = 'active', int $perPage = 10): LengthAwarePaginator
    {
        return $this->query($tab)->paginate($perPage)->withQueryString();
    }
}
