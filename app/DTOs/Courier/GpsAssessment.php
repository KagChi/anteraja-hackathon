<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

use App\Enums\GpsLevel;

/**
 * What the detector made of one fix window.
 */
final readonly class GpsAssessment
{
    /**
     * @param  list<array{code: string, label: string, detail: string, severity: string}>  $reasons
     * @param  array{count: int, span_seconds: int, distinct_points: int}|null  $fixWindow
     */
    public function __construct(
        public GpsLevel $level,
        public array $reasons = [],
        public ?float $accuracyM = null,
        public ?array $fixWindow = null,
        public ?float $impliedSpeedKmh = null,
    ) {}

    public static function clean(): self
    {
        return new self(level: GpsLevel::CLEAN);
    }

    public function blocked(): bool
    {
        return $this->level === GpsLevel::BLOCKED;
    }

    /**
     * @return list<string>
     */
    public function reasonLabels(): array
    {
        return array_values(array_map(static fn (array $reason): string => $reason['label'], $this->reasons));
    }
}
