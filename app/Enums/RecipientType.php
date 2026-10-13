<?php

declare(strict_types=1);

namespace App\Enums;

enum RecipientType: string
{
    case BUYER = 'BUYER';
    case COURIER = 'COURIER';
    case ADMIN = 'ADMIN';
}
