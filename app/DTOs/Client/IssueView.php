<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class IssueView
{
    /**
     * @param  list<string>  $productNames
     * @param  list<TimelineEntry>  $timeline
     * @param  array<string, string>  $customerService
     */
    public function __construct(
        public string $trackingNumber,
        public string $buyerName,
        public ?string $courierName,
        public array $productNames,
        public string $issueCategoryLabel,
        public string $issueSummary,
        public string $decisionMessage,
        public ?string $decisionCode,
        public ?string $qualityInspectionNote,
        public ?string $coldChainChip,
        public array $timeline,
        public bool $canRequestReplacement,
        public string $statusLabel = 'Pengiriman Terkendala',
        public ?string $replacementTrackingNumber = null,
        public ?string $replacementUrl = null,
        public array $customerService = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'buyer_name' => $this->buyerName,
            'courier_name' => $this->courierName,
            'product_names' => $this->productNames,
            'issue_category_label' => $this->issueCategoryLabel,
            'issue_summary' => $this->issueSummary,
            'decision_message' => $this->decisionMessage,
            'decision_code' => $this->decisionCode,
            'quality_inspection_note' => $this->qualityInspectionNote,
            'cold_chain_chip' => $this->coldChainChip,
            'timeline' => array_map(fn (TimelineEntry $entry) => $entry->toArray(), $this->timeline),
            'can_request_replacement' => $this->canRequestReplacement,
            'status_label' => $this->statusLabel,
            'replacement_tracking_number' => $this->replacementTrackingNumber,
            'replacement_url' => $this->replacementUrl,
            'customer_service' => $this->customerService,
        ];
    }
}
