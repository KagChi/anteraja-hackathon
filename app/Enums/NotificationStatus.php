<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationStatus: string
{
    case QUEUED = 'QUEUED';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
