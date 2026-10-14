<?php

declare(strict_types=1);

namespace App\Http\Requests\Courier;

use App\DTOs\Courier\StartDeliveryData;
use App\Http\Requests\Courier\Concerns\ValidatesGpsSignals;
use App\Support\ChecklistNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class StartDeliveryRequest extends FormRequest
{
    use ValidatesGpsSignals;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'checklist' => ['nullable', 'array'],
            'temperature_c' => ['nullable', 'numeric', 'between:-60,40'],
        ], $this->gpsSignalRules());
    }

    public function toData(): StartDeliveryData
    {
        $rawChecklist = is_array($this->input('checklist')) ? $this->input('checklist') : [];
        $normalized = ChecklistNormalizer::normalize($rawChecklist);

        return new StartDeliveryData(
            checklist: $normalized,
            temperatureC: $this->filled('temperature_c') ? (float) $this->input('temperature_c') : null,
            latitude: $this->filled('latitude') ? (float) $this->input('latitude') : null,
            longitude: $this->filled('longitude') ? (float) $this->input('longitude') : null,
        );
    }
}
