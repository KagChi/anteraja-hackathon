<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimAccountRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
