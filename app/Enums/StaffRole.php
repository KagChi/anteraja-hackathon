<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffRole: string
{
    case ADMIN = 'ADMIN';
    case COURIER = 'COURIER';
}
