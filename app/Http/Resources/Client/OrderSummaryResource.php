<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Models\Package;
use App\Services\Client\OrderPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
final class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderPresenter $presenter */
        $presenter = app(OrderPresenter::class);

        $productSummary = $presenter->formatProductSummary($this->resource);
        $schedule = $presenter->formatSchedule($this->resource);
        $completion = $presenter->formatCompletion($this->resource);

        return [
            'resi' => $this->tracking_number,
            'status' => $presenter->formatStatus($this->resource),
            'product' => $productSummary['product'],
            'product_meta' => $productSummary['product_meta'],
            'recipient' => $this->recipient_name ?? 'Penerima',
            'recipient_area' => $this->recipient_area,
            'eta_date' => $schedule['eta_date'],
            'eta_time' => $schedule['eta_time'],
            'action_required' => $presenter->resolveActionRequired($this->resource),
            'next_screen' => $presenter->resolveNextScreen($this->resource),
            'completed_label' => $completion['completed_label'],
            'completed_at' => $completion['completed_at'],
        ];
    }
}
