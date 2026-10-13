<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\IssueDecision;

final readonly class ResolveIssueData
{
    public function __construct(
        public IssueDecision $decision,
        public string $decisionReason,
        public ?string $qualityInspectionNote = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $decision = $input['decision'] instanceof IssueDecision
            ? $input['decision']
            : IssueDecision::from((string) $input['decision']);

        return new self(
            decision: $decision,
            decisionReason: trim((string) ($input['decision_reason'] ?? '')),
            qualityInspectionNote: isset($input['quality_inspection_note']) && trim((string) $input['quality_inspection_note']) !== ''
                ? trim((string) $input['quality_inspection_note'])
                : null,
        );
    }
}
