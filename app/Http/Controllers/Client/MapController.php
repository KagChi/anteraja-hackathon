<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The screen the customer app's destination picker runs in.
 *
 * NativePHP has no native map element, so the checkout screen embeds a
 * php-mode webview pointed at these routes. The parcel goes to the recipient's
 * door — often not the sender's — so the sender drops the pin themselves
 * instead of seeding it from the device's own GPS. The webview writes the
 * chosen point into the file cache and the native Checkout component polls it
 * back, the reverse of the courier map's flow.
 */
final class MapController extends Controller
{
    /** Cache key the picker writes and the native component reads. */
    public static function cacheKey(): string
    {
        return 'client_map_pick';
    }

    public function show(): View
    {
        return view('native.client.map');
    }

    public function point(): JsonResponse
    {
        /** @var array<string, mixed>|null $pick */
        $pick = Cache::get(self::cacheKey());

        return response()->json([
            'point' => $pick === null ? null : [
                'latitude' => $pick['latitude'] ?? null,
                'longitude' => $pick['longitude'] ?? null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        Cache::put(self::cacheKey(), [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
        ], now()->addHours(6));

        return response()->json(['saved' => true]);
    }
}
