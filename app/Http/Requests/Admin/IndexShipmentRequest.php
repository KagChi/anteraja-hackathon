<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\ShipmentFilters;
use Illuminate\Foundation\Http\FormRequest;

final class IndexShipmentRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'courier' => ['nullable', 'string'],
            'schedule' => ['nullable', 'string', 'in:today,tomorrow,upcoming,all'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ];
    }

    public function toFilters(): ShipmentFilters
    {
        return ShipmentFilters::fromArray($this->validated());
    }
}
