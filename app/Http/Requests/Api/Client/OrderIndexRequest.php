<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

final class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:active,completed,issue,all'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
