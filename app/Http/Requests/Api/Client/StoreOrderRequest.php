<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use App\Enums\AddressType;
use App\Enums\ServiceType;
use App\Support\DisplayTime;
use App\Support\PhoneFormatter;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * The customer app's checkout form: what to send, when, and where.
 *
 * The recipient fields double as the customer's own delivery profile, so the
 * form is prefilled from it and saved back on submit.
 */
final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recipient = $this->input('recipient');

        if (is_array($recipient) && isset($recipient['phone']) && $recipient['phone'] !== '') {
            $recipient['phone'] = PhoneFormatter::toE164Id((string) $recipient['phone']);
        }

        if (is_array($recipient) && isset($recipient['address_type'])) {
            $normalized = AddressType::fromInput((string) $recipient['address_type']);
            if ($normalized !== null) {
                $recipient['address_type'] = $normalized->value;
            }
        }

        if (is_array($recipient)) {
            $this->merge(['recipient' => $recipient]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'array'],
            'recipient.name' => ['required', 'string', 'min:2', 'max:120'],
            'recipient.phone' => ['required', 'string', 'max:20'],
            'recipient.address' => ['required', 'string', 'min:5', 'max:255'],
            'recipient.area' => ['nullable', 'string', 'max:120'],
            'recipient.address_type' => ['nullable', new Enum(AddressType::class)],
            'recipient.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'recipient.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:recipient.latitude'],

            'service_type' => ['required', new Enum(ServiceType::class)],
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->toDateString()],
            'window_start' => ['required', 'date_format:H:i'],
            'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'handling_instructions' => ['nullable', 'string', 'max:300'],

            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_date.after_or_equal' => 'Tanggal pengiriman tidak boleh di masa lalu.',
            'window_end.after' => 'Jam selesai harus setelah jam mulai.',
            'items.required' => 'Pilih minimal satu produk untuk dikirim.',
        ];
    }
}
