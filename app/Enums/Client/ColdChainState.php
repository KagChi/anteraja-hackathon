<?php

declare(strict_types=1);

namespace App\Enums\Client;

enum ColdChainState: string
{
    case MAINTAINED = 'Terjaga';
    case NEEDS_ATTENTION = 'PerluPerhatian';
    case MONITORED = 'Dipantau';
}
