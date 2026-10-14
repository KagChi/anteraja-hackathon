<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Models\Package;

final class ClientLinkService
{
    /**
     * Absolute links must not inherit the request host: n8n calls this app over
     * the in-cluster service name, and a link built from that host cannot be
     * opened by anyone outside the cluster.
     */
    private function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function entry(Package $package): string
    {
        if (! empty($package->client_token)) {
            return $this->base()."/api/client/{$package->client_token}";
        }

        return $this->base()."/api/client/orders/{$package->tracking_number}";
    }

    public function tracking(Package $package): string
    {
        if (! empty($package->client_token)) {
            return $this->base()."/api/client/{$package->client_token}/tracking";
        }

        return $this->base()."/api/client/orders/{$package->tracking_number}/tracking";
    }

    public function schedule(Package $package): string
    {
        if (! empty($package->client_token)) {
            return $this->base()."/api/client/{$package->client_token}/schedule";
        }

        return $this->base()."/api/client/orders/{$package->tracking_number}/schedule";
    }

    public function issue(Package $package): string
    {
        if (! empty($package->client_token)) {
            return $this->base()."/api/client/{$package->client_token}/issue";
        }

        return $this->base()."/api/client/orders/{$package->tracking_number}/issue";
    }
}
