<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional new delivery window submitted alongside a schedule-confirmation
 * request. Admin and courier share this: leaving the fields out asks the
 * recipient to confirm the standing window, filling them in proposes a new one.
 */
final class ScheduleWindowRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'scheduled_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'required_with:scheduled_time_window'],
            'scheduled_time_window' => ['nullable', 'string', 'required_with:scheduled_date'],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function toWindow(): ?array {
        if (! $this->filled('scheduled_date') || ! $this->filled('scheduled_time_window')) {
            return null;
        }

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
