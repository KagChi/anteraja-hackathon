<?php

declare(strict_types=1);

namespace App\Enums\Client;

enum ClientScreen: string
{
    case SCHEDULE = 'schedule';
    case ISSUE = 'issue';
    case TRACKING = 'tracking';
}
