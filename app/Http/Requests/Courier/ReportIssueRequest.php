<?php

declare(strict_types=1);

namespace App\Http\Requests\Courier;

use App\DTOs\Courier\ReportIssueData;
use App\Enums\IssueCategory;
use App\Support\ChecklistNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class ReportIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('issue_category') && $this->has('jenis_kendala')) {
            $this->merge(['issue_category' => $this->input('jenis_kendala')]);
        }

        if ($this->has('issue_category')) {
            $cat = (string) $this->input('issue_category');
            if ($cat === 'pembayaran_cod_ditolak') {
                $this->merge(['issue_category' => 'cod_ditolak']);
            } elseif ($cat === 'akses_ditutup') {
                $this->merge(['issue_category' => 'alamat_tidak_ditemukan']);
            }
        }

        if (! $this->has('description')) {
            if ($this->has('catatan_kendala')) {
                $this->merge(['description' => $this->input('catatan_kendala')]);
            } elseif ($this->has('note')) {
                $this->merge(['description' => $this->input('note')]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'issue_category' => ['required', new Enum(IssueCategory::class)],
            'description' => ['required', 'string', 'min:10', 'max:200'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'temperature_c' => ['required_if:issue_category,suhu_naik', 'nullable', 'numeric', 'between:-60,40'],
            'checklist' => ['nullable', 'array'],
        ];
    }

    public function toData(string $photoPath): ReportIssueData
    {
        $category = IssueCategory::tryFrom((string) $this->input('issue_category')) ?? IssueCategory::KEMASAN_BOCOR;
        $rawChecklist = is_array($this->input('checklist')) ? $this->input('checklist') : [];
        $normalized = ChecklistNormalizer::normalize($rawChecklist);

        return new ReportIssueData(
            issueCategory: $category,
            description: (string) $this->input('description'),
            photoPath: $photoPath,
            temperatureC: $this->filled('temperature_c') ? (float) $this->input('temperature_c') : null,
            checklist: $normalized,
        );
    }
}
