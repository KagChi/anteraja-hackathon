<?php

declare(strict_types=1);

namespace App\Http\Requests\Courier;

use App\DTOs\Courier\ConfirmDeliveryData;
use App\Enums\RecipientRelation;
use App\Http\Requests\Courier\Concerns\ValidatesGpsSignals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class ConfirmDeliveryRequest extends FormRequest
{
    use ValidatesGpsSignals;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('recipient_relation')) {
            $relation = RecipientRelation::fromInput((string) $this->input('recipient_relation'));
            if ($relation !== null) {
                $this->merge(['recipient_relation' => $relation->value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'received_by' => ['required', 'string', 'min:2', 'max:120'],
            'recipient_relation' => ['required', new Enum(RecipientRelation::class)],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'receipt_note' => ['nullable', 'string', 'max:300'],
        ], $this->gpsSignalRules());
    }

    public function toData(string $photoPath): ConfirmDeliveryData
    {
        $relation = RecipientRelation::tryFrom((string) $this->input('recipient_relation')) ?? RecipientRelation::SELF;

        return new ConfirmDeliveryData(
            receivedBy: (string) $this->input('received_by'),
            recipientRelation: $relation,
            photoPath: $photoPath,
            receiptNote: $this->filled('receipt_note') ? (string) $this->input('receipt_note') : null,
        );
    }
}
