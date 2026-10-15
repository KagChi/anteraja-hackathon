<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Courier\MapController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The courier map the delivery webview loads: the Leaflet page and the fix
 * route it polls. The native component writes the cache; these routes only
 * read it, so they stay reachable from the device's own PHP runtime.
 */
final class CourierMapTest extends TestCase {
    private const RESI = 'ANR-1234';

    public function test_the_map_page_renders_leaflet_for_the_shipment(): void {
        $response = $this->get('/courier/map/'.self::RESI);

        $response->assertOk();
        $response->assertSee('leaflet', false);
        $response->assertSee(self::RESI, false);
    }

    public function test_the_fix_route_reports_the_cached_location(): void {
        Cache::put(MapController::cacheKey(self::RESI), [
            'destination_latitude' => -6.229746,
            'destination_longitude' => 106.848529,
            'radius_m' => 30,
            'latitude' => -6.22,
            'longitude' => 106.84,
            'accuracy_m' => 8.5,
            'at' => '2026-10-15T10:00:00+00:00',
        ], now()->addMinutes(30));

        $response = $this->getJson('/courier/map/'.self::RESI.'/fix');

        $response->assertOk()
            ->assertJsonPath('destination.latitude', -6.229746)
            ->assertJsonPath('destination.longitude', 106.848529)
            ->assertJsonPath('radius_m', 30)
            ->assertJsonPath('fix.latitude', -6.22)
            ->assertJsonPath('fix.accuracy_m', 8.5);
    }

    public function test_the_fix_route_is_empty_without_a_cached_context(): void {
        $this->getJson('/courier/map/'.self::RESI.'/fix')
            ->assertOk()
            ->assertJsonPath('destination', null)
            ->assertJsonPath('fix', null);
    }
}
