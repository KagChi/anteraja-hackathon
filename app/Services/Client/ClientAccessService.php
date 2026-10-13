<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Enums\PackageStatus;
use App\Exceptions\ClientLinkExpired;
use App\Exceptions\ClientLinkInvalid;
use App\Models\Package;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ClientAccessService
{
    public function resolve(string $token, ?string $clientIp = null): Package
    {
        $ip = $clientIp ?? request()->ip() ?? '127.0.0.1';
        $limiterKey = 'client-lookup-failed:'.$ip;

        if (RateLimiter::tooManyAttempts($limiterKey, 20)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            throw new HttpException(429, "Terlalu banyak percobaan pencarian token. Silakan coba lagi dalam {$seconds} detik.");
        }

        // Token must be hex string between 32 and 128 characters
        if (! preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
            RateLimiter::hit($limiterKey, 60);
            throw new ClientLinkInvalid;
        }

        /** @var Package|null $package */
        $package = Package::where('client_token', $token)
            ->with(['buyer', 'courier', 'items.product'])
            ->first();

        if ($package === null) {
            RateLimiter::hit($limiterKey, 60);
            throw new ClientLinkInvalid;
        }

        // Check expiration for terminal statuses
        $ttlDays = (int) config('coldchain.client.link_ttl_days', 30);
        $terminalStatuses = [
            PackageStatus::RECEIVED,
            PackageStatus::RETURNED,
            PackageStatus::CLOSED,
        ];

        if (in_array($package->status, $terminalStatuses, true)) {
            $lastUpdated = $package->updated_at;
            if ($lastUpdated instanceof CarbonInterface && $lastUpdated->diffInDays(now()) > $ttlDays) {
                throw new ClientLinkExpired;
            }
        }

        RateLimiter::clear($limiterKey);

        return $package;
    }
}
