<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class ColdChainSummary
{
    public function __construct(
        public string $temperatureLabel,
        public string $temperatureState,
        public string $chainState,
        public bool $isMeasured,
        public ?string $recordedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'temperature_label' => $this->temperatureLabel,
            'temperature_state' => $this->temperatureState,
            'chain_state' => $this->chainState,
            'is_measured' => $this->isMeasured,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
