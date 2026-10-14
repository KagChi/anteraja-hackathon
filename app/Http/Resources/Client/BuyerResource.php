<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Models\Buyer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Buyer
 */
final class BuyerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'area' => $this->area,
            'address_type' => $this->address_type?->value ?? 'RESIDENTIAL',
        ];
    }
}
