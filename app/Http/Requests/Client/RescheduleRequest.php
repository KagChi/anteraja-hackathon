<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\DTOs\Client\RescheduleRequestData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RescheduleRequest extends FormRequest
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
        $slots = config('coldchain.client.slots', [
            '09:00-11:00',
            '11:00-13:00',
            '13:00-15:00',
            '15:00-17:00',
            '17:00-19:00',
        ]);

        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'slot' => ['required', 'string', 'in:'.implode(',', $slots)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $dateStr = (string) $this->input('date');
                $slotStr = (string) $this->input('slot');

                $maxDays = (int) config('coldchain.client.reschedule_max_days', 14);
                $maxDate = now('Asia/Jakarta')->addDays($maxDays)->format('Y-m-d');

                if ($dateStr > $maxDate) {
                    $validator->errors()->add('date', "Tanggal pengantaran maksimal {$maxDays} hari ke depan ({$maxDate}).");

                    return;
                }

                $slotStart = explode('-', $slotStr)[0];
                $proposedDateTime = CarbonImmutable::parse("{$dateStr} {$slotStart}", 'Asia/Jakarta');

                $minLeadHours = (int) config('coldchain.client.min_lead_hours', 2);
                $earliestAllowed = now('Asia/Jakarta')->addHours($minLeadHours);

                if ($proposedDateTime->lessThan($earliestAllowed)) {
                    $validator->errors()->add('slot', "Waktu pengantaran minimal {$minLeadHours} jam dari sekarang.");
                }
            },
        ];
    }

    public function toData(): RescheduleRequestData
    {
        $date = (string) $this->input('date');
        $slot = (string) $this->input('slot');
        [$slotStart, $slotEnd] = explode('-', $slot);

        $proposed = CarbonImmutable::parse("{$date} {$slotStart}", 'Asia/Jakarta')->setTimezone('UTC');
        $proposedEnd = CarbonImmutable::parse("{$date} {$slotEnd}", 'Asia/Jakarta')->setTimezone('UTC');

        return new RescheduleRequestData(
            date: $date,
            slot: $slot,
            proposedScheduledAt: $proposed,
            proposedScheduledEndAt: $proposedEnd,
        );
    }
}
