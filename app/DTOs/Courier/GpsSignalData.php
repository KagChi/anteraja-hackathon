<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

use App\Services\Gps\FakeGpsDetector;

/**
 * The fix window the courier app ships with a depart / arrive / POD call.
 *
 * The app watches the device position and keeps the last N fixes; the server
 * re-derives its own verdict from them and stays authoritative. An empty window
 * is valid: the courier simply gets no GPS verdict.
 */
final readonly class GpsSignalData
{
    /**
     * @param  list<GpsFixData>  $fixes  oldest first
     */
    public function __construct(
        public array $fixes = [],
        public ?int $deviceTimestamp = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw, ?int $deviceTimestamp = null): self
    {
        $fixes = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $fix = GpsFixData::fromArray($entry);
            if ($fix !== null) {
                $fixes[] = $fix;
            }
        }

        // Oldest first: the heuristics walk the window in time order.
        usort($fixes, static fn (GpsFixData $a, GpsFixData $b): int => ($a->timestamp ?? 0) <=> ($b->timestamp ?? 0));

        return new self(
            fixes: array_slice($fixes, -FakeGpsDetector::MAX_FIX_WINDOW),
            deviceTimestamp: $deviceTimestamp,
        );
    }

    public function isEmpty(): bool
    {
        return $this->fixes === [];
    }

    public function latest(): ?GpsFixData
    {
        return $this->fixes === [] ? null : $this->fixes[array_key_last($this->fixes)];
    }
}
