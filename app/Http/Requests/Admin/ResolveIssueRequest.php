<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\ResolveIssueData;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveIssueRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:RETURN,RESEND,PROCEED,RESCHEDULE,RECHECK'],
            'decision_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'quality_inspection_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): ResolveIssueData
    {
        return ResolveIssueData::fromArray($this->validated());
    }
}
