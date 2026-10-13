<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class CargoLine
{
    public function __construct(
        public string $category,
        public string $name,
        public string $quantityLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'name' => $this->name,
            'quantity_label' => $this->quantityLabel,
        ];
    }
}
