<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\Facades\SecureStorage;

/**
 * Where the mobile shells keep their API bearer token.
 *
 * On a device the token lives in the platform keychain / keystore. Off-device
 * — component tests, the dev server — SecureStorage has no bridge to talk to,
 * so the same value falls back to the cache store and survives within the
 * process, which is what a screen reads after a login.
 */
final class MobileToken {
    public const CLIENT = 'client';

    public const COURIER = 'courier';

    public static function get(string $realm): ?string {
        $key = self::key($realm);

        if (function_exists('nativephp_call')) {
            $secure = SecureStorage::get($key);
            if (is_string($secure) && $secure !== '') {
                return $secure;
            }
        }

        $cached = Cache::get($key);

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    public static function set(string $realm, string $token): void {
        $key = self::key($realm);

        if (function_exists('nativephp_call')) {
            SecureStorage::set($key, $token);
        }

        Cache::put($key, $token);
    }

    public static function clear(string $realm): void {
        $key = self::key($realm);

        if (function_exists('nativephp_call')) {
            SecureStorage::delete($key);
        }

        Cache::forget($key);
    }

    private static function key(string $realm): string {
        return "mobile.{$realm}.token";
    }
}
