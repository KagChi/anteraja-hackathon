<?php

declare(strict_types=1);

namespace App\DTOs\Client;

final readonly class ScheduleView
{
    /**
     * @param  list<CargoLine>  $cargoItems
     * @param  list<string>  $availableSlots
     */
    public function __construct(
        public string $trackingNumber,
        public string $status,
        public string $statusLabel,
        public string $buyerName,
        public string $buyerAddress,
        public array $cargoItems,
        public float $totalWeightKg,
        public string $coldGuaranteedBadge,
        public string $handlingNote,
        public string $scheduledDelivery,
        public bool $hasColdPriority,
        public ?string $courierName,
        public ?string $courierBadge,
        public bool $canAction,
        public array $availableSlots = [],
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
            'buyer_name' => $this->buyerName,
            'buyer_address' => $this->buyerAddress,
            'cargo_items' => array_map(fn (CargoLine $item) => $item->toArray(), $this->cargoItems),
            'total_weight_kg' => $this->totalWeightKg,
            'cold_guaranteed_badge' => $this->coldGuaranteedBadge,
            'handling_note' => $this->handlingNote,
            'scheduled_delivery' => $this->scheduledDelivery,
            'has_cold_priority' => $this->hasColdPriority,
            'courier_name' => $this->courierName,
            'courier_badge' => $this->courierBadge,
            'can_action' => $this->canAction,
            'available_slots' => $this->availableSlots,
        ];
    }
}
