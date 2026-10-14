<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Models\Package;

final class ClientLinkService
{
    public function entry(Package $package): string
    {
        if (! empty($package->client_token)) {
            return url("/api/client/{$package->client_token}");
        }

        return url("/api/client/orders/{$package->tracking_number}");
    }

    public function tracking(Package $package): string
    {
        if (! empty($package->client_token)) {
            return url("/api/client/{$package->client_token}/tracking");
        }

        return url("/api/client/orders/{$package->tracking_number}/tracking");
    }

    public function schedule(Package $package): string
    {
        if (! empty($package->client_token)) {
            return url("/api/client/{$package->client_token}/schedule");
        }

        return url("/api/client/orders/{$package->tracking_number}/schedule");
    }

    public function issue(Package $package): string
    {
        if (! empty($package->client_token)) {
            return url("/api/client/{$package->client_token}/issue");
        }

        return url("/api/client/orders/{$package->tracking_number}/issue");
    }
}
