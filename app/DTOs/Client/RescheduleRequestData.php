<?php

declare(strict_types=1);

namespace App\DTOs\Client;

use Carbon\CarbonImmutable;

final readonly class RescheduleRequestData
{
    public function __construct(
        public string $date,
        public string $slot,
        public CarbonImmutable $proposedScheduledAt,
        public CarbonImmutable $proposedScheduledEndAt,
    ) {}
}
