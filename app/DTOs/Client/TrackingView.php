<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class TrackingView
{
    /**
     * @param  list<TimelineEntry>  $timeline
     * @param  array{message: string, url: string}|null  $issueBanner
     */
    public function __construct(
        public string $trackingNumber,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $estimatedDelivery,
        public string $buyerName,
        public string $buyerArea,
        public ?string $courierName,
        public ?string $coolerBoxCode,
        public ?ColdChainSummary $coldChain,
        public array $timeline,
        public bool $canContactCourier,
        public ?string $courierPhone,
        public ?array $issueBanner = null,
        public ?string $rescheduleBanner = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'status_tone' => $this->statusTone,
            'estimated_delivery' => $this->estimatedDelivery,
            'buyer_name' => $this->buyerName,
            'buyer_area' => $this->buyerArea,
            'courier_name' => $this->courierName,
            'cooler_box_code' => $this->coolerBoxCode,
            'cold_chain' => $this->coldChain?->toArray(),
            'timeline' => array_map(fn (TimelineEntry $entry) => $entry->toArray(), $this->timeline),
            'can_contact_courier' => $this->canContactCourier,
            'courier_phone' => $this->courierPhone,
            'issue_banner' => $this->issueBanner,
            'reschedule_banner' => $this->rescheduleBanner,
        ];
    }
}
