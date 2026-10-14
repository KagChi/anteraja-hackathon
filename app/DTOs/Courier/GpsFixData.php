<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

/**
 * One raw position the courier's device reported.
 *
 * The timestamp is the device clock in epoch milliseconds, which the server
 * compares against its own clock and against the other fixes in the window.
 */
final readonly class GpsFixData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $accuracy = null,
        public ?int $timestamp = null,
    ) {}

    /**
     * @param  array<string, mixed>  $fix
     */
    public static function fromArray(array $fix): ?self
    {
        if (! isset($fix['latitude'], $fix['longitude'])) {
            return null;
        }

        if (! is_numeric($fix['latitude']) || ! is_numeric($fix['longitude'])) {
            return null;
        }

        $accuracy = isset($fix['accuracy']) && is_numeric($fix['accuracy']) ? (float) $fix['accuracy'] : null;
        $timestamp = isset($fix['timestamp']) && is_numeric($fix['timestamp']) ? (int) $fix['timestamp'] : null;

        return new self(
            latitude: (float) $fix['latitude'],
            longitude: (float) $fix['longitude'],
            accuracy: $accuracy,
            timestamp: $timestamp,
        );
    }
}
