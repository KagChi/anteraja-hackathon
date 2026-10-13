<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryStage: string
{
    case PENDING = 'PENDING';
    case DEPARTED = 'DEPARTED';
    case ARRIVED = 'ARRIVED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Berangkat',
            self::DEPARTED => 'Sedang Diantar',
            self::ARRIVED => 'Tiba di Lokasi',
        };
    }
}
