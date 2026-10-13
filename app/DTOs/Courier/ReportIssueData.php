<?php

declare(strict_types=1);

namespace App\DTOs\Courier;

use App\Enums\IssueCategory;

final readonly class ReportIssueData
{
    /**
     * @param  array<string, array{answer: bool, detail: string|null}>  $checklist
     */
    public function __construct(
        public IssueCategory $issueCategory,
        public string $description,
        public string $photoPath,
        public ?float $temperatureC = null,
        public array $checklist = [],
    ) {}
}
