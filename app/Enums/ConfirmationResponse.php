<?php

declare(strict_types=1);

namespace App\Enums;

enum ConfirmationResponse: string
{
    case PENDING = 'PENDING';
    case READY = 'READY';
    case RESCHEDULE_REQUESTED = 'RESCHEDULE_REQUESTED';
}
