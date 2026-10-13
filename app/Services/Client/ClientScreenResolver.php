<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\Client\ClientScreen;
use App\Enums\PackageStatus;
use App\Models\Package;

final class ClientScreenResolver
{
    public function resolve(Package $package): ClientScreen
    {
        if ($package->status === PackageStatus::AWAITING_CONFIRMATION) {
            return ClientScreen::SCHEDULE;
        }

        if (
            in_array($package->status, [PackageStatus::ISSUE_REPORTED, PackageStatus::RETURNED], true)
            && $package->conditionChecks()->whereHas('issueReport')->exists()
        ) {
            return ClientScreen::ISSUE;
        }

        return ClientScreen::TRACKING;
    }
}
