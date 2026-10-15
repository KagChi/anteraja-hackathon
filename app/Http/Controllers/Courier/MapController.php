<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The screen the courier app's Leaflet map runs in.
 *
 * NativePHP has no native map element, so the delivery screen embeds a
 * php-mode webview pointed at these two routes. The webview gets its own PHP
 * runtime on the device and answers from `http://127.0.0.1`, which makes the
 * polling route same-origin. The native Delivery component writes the latest
 * device fix into the file cache as it streams, and this page reads it back —
 * so the marker moves without ever reloading the webview.
 */
final class MapController extends Controller {
    /** Cache key the native component writes; one slot per shipment. */
    public static function cacheKey(string $resi): string {
        return "courier_map:{$resi}";
    }

    public function show(string $resi): View {
        return view('native.courier.map', ['resi' => $resi]);
    }

    public function fix(string $resi): JsonResponse {
        /** @var array<string, mixed>|null $context */
        $context = Cache::get(self::cacheKey($resi));

        return response()->json([
            'destination' => $context === null ? null : [
                'latitude' => $context['destination_latitude'] ?? null,
                'longitude' => $context['destination_longitude'] ?? null,
            ],
            'radius_m' => $context['radius_m'] ?? null,
            'fix' => $context === null || ! isset($context['latitude']) ? null : [
                'latitude' => $context['latitude'],
                'longitude' => $context['longitude'],
                'accuracy_m' => $context['accuracy_m'] ?? null,
                'at' => $context['at'] ?? null,
            ],
        ]);
    }
}
