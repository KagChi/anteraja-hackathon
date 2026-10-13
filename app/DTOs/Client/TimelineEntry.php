<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class TimelineEntry
{
    public function __construct(
        public string $title,
        public string $description,
        public string $time,
        public bool $isAccent = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'time' => $this->time,
            'is_accent' => $this->isAccent,
        ];
    }
}
