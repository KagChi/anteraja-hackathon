<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which door a shipment came through: the hub console, or the customer app.
 */
enum PackageSource: string
{
    case ADMIN = 'ADMIN';
    case CLIENT_APP = 'CLIENT_APP';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Hub',
            self::CLIENT_APP => 'Aplikasi Pelanggan',
        };
    }

    /** Short marker for the queue row badge. */
    public function badge(): string
    {
        return match ($this) {
            self::ADMIN => 'HUB',
            self::CLIENT_APP => 'APP',
        };
    }
}
