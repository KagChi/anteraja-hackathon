<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryMilestoneEnum: string
{
    case DEPARTED = 'DEPARTED';
    case ARRIVED = 'ARRIVED';

    public function label(): string
    {
        return match ($this) {
            self::DEPARTED => 'Berangkat',
            self::ARRIVED => 'Tiba di Lokasi',
        };
    }
}
