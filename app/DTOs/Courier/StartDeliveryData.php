<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

final readonly class StartDeliveryData
{
    /**
     * @param  array<string, array{answer: bool, detail: string|null}>  $checklist
     */
    public function __construct(
        public array $checklist,
        public ?float $temperatureC = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
