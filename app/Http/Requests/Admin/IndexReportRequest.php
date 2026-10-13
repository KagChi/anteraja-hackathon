<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;

final class IndexReportRequest extends FormRequest
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
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toFilters(): ReportFilters
    {
        return ReportFilters::fromArray($this->validated());
    }
}
