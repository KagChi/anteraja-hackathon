<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ShipmentFilters
{
    public function __construct(
        public ?string $q = null,
        public ?string $status = null,
        public ?string $courier = null,
        public string $schedule = 'today',
        public int $perPage = 10,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $q = isset($input['q']) && trim((string) $input['q']) !== '' ? trim((string) $input['q']) : null;
        $status = isset($input['status']) && $input['status'] !== 'all' && trim((string) $input['status']) !== '' ? (string) $input['status'] : null;
        $courier = isset($input['courier']) && $input['courier'] !== 'all' && trim((string) $input['courier']) !== '' ? (string) $input['courier'] : null;
        $schedule = isset($input['schedule']) && in_array($input['schedule'], ['today', 'tomorrow', 'upcoming', 'all'], true) ? (string) $input['schedule'] : 'today';
        $perPage = isset($input['per_page']) && in_array((int) $input['per_page'], [10, 25, 50], true) ? (int) $input['per_page'] : 10;

        return new self(
            q: $q,
            status: $status,
            courier: $courier,
            schedule: $schedule,
            perPage: $perPage,
        );
    }
}
