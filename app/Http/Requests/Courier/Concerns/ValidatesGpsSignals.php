<?php

declare(strict_types=1);

namespace App\Http\Requests\Courier\Concerns;

use App\DTOs\Courier\GpsSignalData;
use App\Services\Gps\FakeGpsDetector;

/**
 * The fix window the courier app ships alongside an action.
 *
 * Every GPS-carrying courier request reads it the same way: the window stays
 * optional, because a device without a fix must not be able to lock the courier
 * out of the delivery flow.
 */
trait ValidatesGpsSignals
{
    /**
     * @return array<string, mixed>
     */
    protected function gpsSignalRules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'device_timestamp' => ['nullable', 'integer'],
            'gps_signals' => ['nullable', 'array', 'max:'.FakeGpsDetector::MAX_FIX_WINDOW],
            'gps_signals.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'gps_signals.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_signals.*.accuracy' => ['nullable', 'numeric', 'between:0,10000'],
            'gps_signals.*.timestamp' => ['nullable', 'integer'],
        ];
    }

    public function gpsSignals(): GpsSignalData
    {
        $raw = $this->input('gps_signals');
        $deviceTimestamp = $this->input('device_timestamp');

        return GpsSignalData::fromArray(
            is_array($raw) ? $raw : [],
            is_numeric($deviceTimestamp) ? (int) $deviceTimestamp : null,
        );
    }

    public function fixLatitude(): ?float
    {
        return $this->filled('latitude') ? (float) $this->input('latitude') : null;
    }

    public function fixLongitude(): ?float
    {
        return $this->filled('longitude') ? (float) $this->input('longitude') : null;
    }
}
