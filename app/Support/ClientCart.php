<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The customer app's basket.
 *
 * Screens are separate native components with no shared parent state, so the
 * basket lives in the cache store the shells build with the file driver — the
 * same place MobileToken keeps its value. It holds product ids
 * and quantities only; names and prices are always read back from the catalog,
 * so a stale basket can never quote a price the server would not honour.
 */
final class ClientCart
{
    public const KEY = 'mobile.client.cart';

    public const MAX_QUANTITY = 20;

    /**
     * @return array<int, int> product id => quantity
     */
    public static function items(): array
    {
        $raw = Cache::get(self::KEY);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $productId => $quantity) {
            $id = (int) $productId;
            $qty = (int) $quantity;

            if ($id > 0 && $qty > 0) {
                $items[$id] = min(self::MAX_QUANTITY, $qty);
            }
        }

        return $items;
    }

    public static function quantity(int $productId): int
    {
        return self::items()[$productId] ?? 0;
    }

    public static function add(int $productId, int $quantity = 1): void
    {
        if ($productId <= 0 || $quantity <= 0) {
            return;
        }

        $items = self::items();
        $items[$productId] = min(self::MAX_QUANTITY, ($items[$productId] ?? 0) + $quantity);

        self::put($items);
    }

    public static function set(int $productId, int $quantity): void
    {
        if ($productId <= 0) {
            return;
        }

        $items = self::items();

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = min(self::MAX_QUANTITY, $quantity);
        }

        self::put($items);
    }

    public static function remove(int $productId): void
    {
        $items = self::items();
        unset($items[$productId]);

        self::put($items);
    }

    public static function clear(): void
    {
        Cache::forget(self::KEY);
    }

    /** Total pieces in the basket, which is what the tab badge shows. */
    public static function count(): int
    {
        return array_sum(self::items());
    }

    /**
     * @param  array<int, int>  $items
     */
    private static function put(array $items): void
    {
        if ($items === []) {
            Cache::forget(self::KEY);

            return;
        }

        Cache::put(self::KEY, $items);
    }
}
