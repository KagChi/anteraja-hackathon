<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignCourierRequest extends FormRequest
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
            'courier_id' => [
                'required',
                'integer',
                Rule::exists('staff', 'id')->where(function ($query) {
                    $query->where('role', StaffRole::COURIER->value)
                        ->where('is_active', true);
                }),
            ],
        ];
    }
}
