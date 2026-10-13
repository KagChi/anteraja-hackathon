<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\HistoryFilters;
use Illuminate\Foundation\Http\FormRequest;

final class IndexHistoryRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:RECEIVED,RETURNED,CLOSED,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toFilters(): HistoryFilters
    {
        return HistoryFilters::fromArray($this->validated());
    }
}
