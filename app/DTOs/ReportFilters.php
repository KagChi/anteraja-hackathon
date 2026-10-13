<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\Carbon;

final readonly class ReportFilters
{
    public function __construct(
        public string $date,
        public ?string $category = null,
        public int $page = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $date = isset($input['date']) && is_string($input['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date']) === 1
            ? min($input['date'], $today)
            : $today;

        $category = isset($input['category']) && $input['category'] !== 'all' && trim((string) $input['category']) !== ''
            ? (string) $input['category']
            : null;

        $page = isset($input['page']) && (int) $input['page'] > 0 ? (int) $input['page'] : 1;

        return new self(
            date: $date,
            category: $category,
            page: $page,
        );
    }
}
