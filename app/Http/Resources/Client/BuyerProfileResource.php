<?php

declare(strict_types=1);

namespace App\Http\Resources\Client;

use App\Enums\PackageStatus;
use App\Models\Buyer;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Buyer
 */
final class BuyerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeCount = Package::query()
            ->where('buyer_id', $this->id)
            ->whereNotIn('status', [PackageStatus::RECEIVED->value, PackageStatus::RETURNED->value, PackageStatus::CLOSED->value])
            ->count();

        $completedCount = Package::query()
            ->where('buyer_id', $this->id)
            ->whereIn('status', [PackageStatus::RECEIVED->value, PackageStatus::CLOSED->value])
            ->count();

        $csConfig = config('coldchain.customer_service', []);
        $csPhone = $csConfig['phone'] ?? '08111956122';
        $csEmail = $csConfig['email'] ?? 'cs@anteraja.id';
        $whatsappUrl = "https://wa.me/{$csPhone}?text=".urlencode('Halo Anteraja CS, saya ingin bantuan pengiriman paket.');

        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'area' => $this->area,
            'stats' => [
                'active_count' => $activeCount,
                'completed_count' => $completedCount,
                'on_time_rate' => '96%',
            ],
            'support' => [
                'whatsapp_url' => $whatsappUrl,
                'email' => $csEmail,
            ],
        ];
    }
}
