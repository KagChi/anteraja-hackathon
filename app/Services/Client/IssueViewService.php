<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\DTOs\Client\IssueView;
use App\Models\Package;

final class IssueViewService
{
    public function __construct(
        private readonly ColdChainSummaryService $coldChainSummaryService,
        private readonly ClientTimelineBuilder $timelineBuilder,
        private readonly ReplacementEligibility $replacementEligibility,
        private readonly ClientLinkService $linkService,
    ) {}

    public function build(Package $package): IssueView
    {
        $latestIssue = $package->latestIssue;

        $cat = $latestIssue?->issue_category instanceof \BackedEnum
            ? $latestIssue->issue_category->value
            : (string) ($latestIssue?->issue_category ?? 'default');

        $catKey = "client.issue.summary.{$cat}";
        $summary = __($catKey, [], 'id');
        if ($summary === $catKey) {
            $summary = __('client.issue.summary.default', [], 'id');
        }

        $dec = $latestIssue?->decision instanceof \BackedEnum
            ? $latestIssue->decision->value
            : ($latestIssue?->decision ? (string) $latestIssue->decision : null);

        if ($dec !== null) {
            $decKey = "client.issue.decision.{$dec}";
            $decisionMessage = __($decKey, [], 'id');
            if ($decisionMessage === $decKey) {
                $decisionMessage = __('client.issue.decision.pending', [], 'id');
            }
        } else {
            $decisionMessage = (string) __('client.issue.decision.pending', [], 'id');
        }

        $coldSummary = $this->coldChainSummaryService->forPackage($package);
        $coldChainChip = $coldSummary->isMeasured
            ? "Suhu: {$coldSummary->temperatureLabel} {$coldSummary->chainState}"
            : null;

        $productNames = $package->items->map(fn ($item) => $item->product->name)->all();

        $eligibility = $this->replacementEligibility->check($package);

        $replacementUrl = $eligibility->childPackage !== null
            ? $this->linkService->schedule($eligibility->childPackage)
            : null;

        /** @var array<string, string> $cs */
        $cs = config('coldchain.customer_service', []);

        return new IssueView(
            trackingNumber: $package->tracking_number,
            statusLabel: (string) __("client.status.{$package->status->value}", [], 'id'),
            buyerName: $package->buyer->name,
            courierName: $package->courier?->name,
            productNames: $productNames,
            issueCategoryLabel: (string) $summary,
            issueSummary: (string) $summary,
            decisionMessage: (string) $decisionMessage,
            decisionCode: $dec,
            qualityInspectionNote: ! empty($latestIssue?->quality_inspection_note) ? $latestIssue->quality_inspection_note : null,
            coldChainChip: $coldChainChip,
            timeline: $this->timelineBuilder->forIssue($package),
            canRequestReplacement: $eligibility->isEligible,
            replacementTrackingNumber: $eligibility->childPackage?->tracking_number,
            replacementUrl: $replacementUrl,
            customerService: $cs,
        );
    }
}
