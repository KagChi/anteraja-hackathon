<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Models\Package;
use App\Support\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The confirmation the customer app shows after checkout: the new resi, the
 * agreed window, and what is in the box.
 *
 * @mixin Package
 */
final class OrderReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = [];
        $total = 0;
        $itemCount = 0;

        foreach ($this->items as $item) {
            $product = $item->product;
            $lineTotal = (int) ($product?->price_idr ?? 0) * (int) $item->quantity;
            $total += $lineTotal;
            $itemCount += (int) $item->quantity;

            $items[] = [
                'name' => $product?->name ?? 'Produk',
                'quantity' => (int) $item->quantity,
                'unit' => $product?->unit ?? 'pack',
                'price_idr' => (int) ($product?->price_idr ?? 0),
                'line_total_idr' => $lineTotal,
            ];
        }

        $scheduledLocal = DisplayTime::toDisplay($this->scheduled_at);
        $endLocal = DisplayTime::toDisplay($this->scheduled_end_at);

        return [
            'tracking_number' => $this->tracking_number,
            'status' => $this->status?->value,
            'status_label' => $this->statusRef?->label ?? (string) $this->status?->value,
            'source' => $this->source?->value,
            'service_type' => $this->service_type?->value,
            'service_label' => $this->service_type?->label(),
            'recipient' => [
                'name' => $this->recipient_name,
                'phone' => $this->recipient_phone,
                'address' => $this->recipient_address,
                'area' => $this->recipient_area,
            ],
            'scheduled_label' => DisplayTime::windowLabel($this->scheduled_at, $this->scheduled_end_at),
            'scheduled_date' => $scheduledLocal?->toDateString(),
            'window_start' => $scheduledLocal?->format('H:i'),
            'window_end' => $endLocal?->format('H:i'),
            'weight_kg' => $this->weight_kg,
            'item_count' => $itemCount,
            'total_idr' => $total,
            'handling_instructions' => $this->handling_instructions,
            'items' => $items,
        ];
    }
}
