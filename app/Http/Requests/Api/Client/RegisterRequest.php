<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use App\Enums\AddressType;
use App\Support\PhoneFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The customer app's sign-up form. No OTP: the phone number is the account
 * identifier and the password is chosen here (the hub can still reach the
 * account through the WhatsApp channel).
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            // Buyers normalise phone on save, so the uniqueness check has to
            // run against the same E.164 shape the column will hold.
            $this->merge(['phone' => PhoneFormatter::toE164Id((string) $this->input('phone'))]);
        }

        if ($this->filled('address_type')) {
            $normalized = AddressType::fromInput((string) $this->input('address_type'));
            if ($normalized !== null) {
                $this->merge(['address_type' => $normalized->value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('buyers', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'area' => ['nullable', 'string', 'max:120'],
            'address_type' => ['nullable', new Enum(AddressType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'Nomor HP ini sudah terdaftar. Silakan masuk.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ];
    }
}
