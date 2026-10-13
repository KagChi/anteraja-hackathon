<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressType: string
{
    case RESIDENTIAL = 'RESIDENTIAL';
    case COMMERCIAL = 'COMMERCIAL';

    public function label(): string
    {
        return match ($this) {
            self::RESIDENTIAL => 'Rumah / Residensial',
            self::COMMERCIAL => 'Kantor / Komersial',
        };
    }
}
