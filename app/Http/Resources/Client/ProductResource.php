<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One catalog card in the customer app.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'price_idr' => $this->price_idr,
            'price_label' => $this->priceLabel(),
            'unit' => $this->unit,
            'weight_kg' => $this->weight_kg,
            'weight_label' => $this->weightLabel(),
            'image_url' => $this->image_path,
            'handling_notes' => $this->handling_notes,
        ];
    }
}
