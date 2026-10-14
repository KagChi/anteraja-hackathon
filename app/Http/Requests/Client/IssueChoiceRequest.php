<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\BuyerIssueChoice;
use Illuminate\Foundation\Http\FormRequest;

final class IssueChoiceRequest extends FormRequest
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
            'choice' => ['required', 'integer', 'between:1,4'],
        ];
    }

    public function toChoice(): BuyerIssueChoice
    {
        return BuyerIssueChoice::fromOption((int) $this->input('choice')) ?? BuyerIssueChoice::DISCUSS;
    }
}
