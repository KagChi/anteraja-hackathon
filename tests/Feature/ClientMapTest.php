<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Client\MapController;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The destination picker the checkout webview loads: the Leaflet page, the
 * point it saves, and the read the native component polls. The reverse of the
 * courier map — here the page writes the cache and the component reads it.
 */
final class ClientMapTest extends TestCase
{
    public function test_the_picker_page_renders_leaflet(): void
    {
        $this->get('/client/map/pick')
            ->assertOk()
            ->assertSee('leaflet', false);
    }

    public function test_the_point_route_reports_the_saved_pick(): void
    {
        Cache::put(MapController::cacheKey(), ['latitude' => -6.229746, 'longitude' => 106.848529]);

        $this->getJson('/client/map/pick/point')
            ->assertOk()
            ->assertJsonPath('point.latitude', -6.229746)
            ->assertJsonPath('point.longitude', 106.848529);
    }

    public function test_the_point_route_is_empty_without_a_pick(): void
    {
        $this->getJson('/client/map/pick/point')
            ->assertOk()
            ->assertJsonPath('point', null);
    }

    public function test_a_pick_is_saved_for_the_component_to_poll(): void
    {
        $this->postJson('/client/map/pick', ['latitude' => -6.9175, 'longitude' => 107.6191])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $pick = Cache::get(MapController::cacheKey());

        $this->assertIsArray($pick);
        $this->assertSame(-6.9175, $pick['latitude']);
        $this->assertSame(107.6191, $pick['longitude']);
    }

    public function test_a_pick_outside_the_map_bounds_is_rejected(): void
    {
        $this->postJson('/client/map/pick', ['latitude' => 120, 'longitude' => 300])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
