<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three courier moments the server validates a GPS fix at.
 */
enum GpsAction: string
{
    case DEPART = 'DEPART';
    case ARRIVE = 'ARRIVE';
    case POD = 'POD';

    public function label(): string
    {
        return match ($this) {
            self::DEPART => 'Berangkat',
            self::ARRIVE => 'Tiba di lokasi',
            self::POD => 'Serah terima',
        };
    }
}
