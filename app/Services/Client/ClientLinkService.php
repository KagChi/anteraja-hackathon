<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Models\Package;

final class ClientLinkService
{
    public function entry(Package $package): string
    {
        return route('api.client.entry', ['token' => $package->client_token]);
    }

    public function tracking(Package $package): string
    {
        return route('api.client.tracking.show', ['token' => $package->client_token]);
    }

    public function schedule(Package $package): string
    {
        return route('api.client.schedule.show', ['token' => $package->client_token]);
    }

    public function issue(Package $package): string
    {
        return route('api.client.issue.show', ['token' => $package->client_token]);
    }
}
