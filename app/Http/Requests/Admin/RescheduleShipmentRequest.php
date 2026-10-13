<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class RescheduleShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'scheduled_time_window' => ['required', 'string'],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function toWindow(): array
    {
        $date = (string) $this->input('scheduled_date');
        $window = (string) $this->input('scheduled_time_window');

        $parts = explode('-', str_replace(' ', '', $window));
        $startStr = $parts[0] ?? '09:00';
        $endStr = $parts[1] ?? '11:00';

        $start = CarbonImmutable::parse("{$date} {$startStr}", 'Asia/Jakarta')->setTimezone('UTC');
        $end = CarbonImmutable::parse("{$date} {$endStr}", 'Asia/Jakarta')->setTimezone('UTC');

        return [$start, $end];
    }
}
