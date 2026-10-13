<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class HistoryFilters
{
    public function __construct(
        public ?string $q = null,
        public ?string $status = null,
        public int $page = 1,
        public ?int $courierId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $q = isset($input['q']) && trim((string) $input['q']) !== '' ? trim((string) $input['q']) : null;
        $status = isset($input['status']) && in_array($input['status'], ['RECEIVED', 'RETURNED', 'CLOSED'], true)
            ? (string) $input['status']
            : null;
        $page = isset($input['page']) && (int) $input['page'] > 0 ? (int) $input['page'] : 1;

        return new self(
            q: $q,
            status: $status,
            page: $page,
        );
    }
}
