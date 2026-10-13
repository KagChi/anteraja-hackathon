<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceType: string
{
    case REGULER = 'REGULER';
    case INSTANT_FROZEN = 'INSTANT_FROZEN';

    public function label(): string
    {
        return match ($this) {
            self::REGULER => 'Reguler',
            self::INSTANT_FROZEN => 'Instant Frozen',
        };
    }
}
