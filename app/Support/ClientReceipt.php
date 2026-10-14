<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The receipt for the order that was just placed.
 *
 * The success screen is a separate native component, so the payload the
 * checkout API returned is handed over through the cache store rather than
 * being re-fetched (the customer API lists shipments, but the created order has
 * not been scheduled yet and would not read the same).
 */
final class ClientReceipt
{
    public const KEY = 'mobile.client.last_receipt';

    /**
     * @param  array<string, mixed>  $receipt
     */
    public static function put(array $receipt): void
    {
        Cache::put(self::KEY, $receipt, now()->addHours(6));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $receipt = Cache::get(self::KEY);

        return is_array($receipt) ? $receipt : null;
    }

    public static function clear(): void
    {
        Cache::forget(self::KEY);
    }
}
