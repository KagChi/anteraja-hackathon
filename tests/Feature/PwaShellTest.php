<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The two installable apps: their shells, their manifests, and the worker that
 * makes them installable.
 *
 * The SPA itself is client-side; what the server owns is the shell it boots
 * from, the manifest the browser installs from, and the offline assets. Each of
 * those is covered here.
 */
final class PwaShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_client_shell_is_public_and_carries_its_app_marker(): void
    {
        $response = $this->get('/client');

        $response->assertOk()
            ->assertSee('data-app="client"', false)
            ->assertSee('/client.webmanifest', false)
            ->assertSee('/icons/client-apple-180.png', false);
    }

    public function test_deep_links_boot_the_shell_so_the_spa_can_route_them(): void
    {
        $this->get('/client/tracking/AJ-FRZ-0012345')
            ->assertOk()
            ->assertSee('data-app="client"', false);

        $this->get('/courier/delivery/AJ-FRZ-0012345?reason=Kemasan%20bocor')
            ->assertOk()
            ->assertSee('data-app="courier"', false);
    }

    public function test_the_courier_shell_carries_its_own_manifest_and_title(): void
    {
        $response = $this->get('/courier');

        $response->assertOk()
            ->assertSee('data-app="courier"', false)
            ->assertSee('/courier.webmanifest', false)
            ->assertSee('Anteraja Kurir');
    }

    public function test_the_client_manifest_describes_an_installable_app(): void
    {
        $response = $this->get('/client.webmanifest');

        $response->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('id', '/client')
            ->assertJsonPath('name', 'Anteraja Frozen')
            ->assertJsonPath('start_url', '/client')
            ->assertJsonPath('scope', '/client')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('lang', 'id')
            ->assertJsonCount(3, 'icons')
            ->assertJsonPath('icons.0.sizes', '192x192')
            ->assertJsonPath('icons.2.purpose', 'maskable');
    }

    public function test_the_courier_manifest_is_a_second_app_on_the_same_origin(): void
    {
        $this->get('/courier.webmanifest')
            ->assertOk()
            ->assertJsonPath('id', '/courier')
            ->assertJsonPath('start_url', '/courier')
            ->assertJsonPath('scope', '/courier')
            ->assertJsonCount(3, 'icons')
            ->assertJsonPath('icons.2.src', '/icons/courier-maskable-512.png');
    }

    public function test_an_unknown_manifest_is_not_served(): void
    {
        $this->get('/kurir.webmanifest')->assertNotFound();
    }

    public function test_the_worker_and_offline_page_are_shipped(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));
        $offline = (string) file_get_contents(public_path('offline.html'));

        // The API is never cached: a stale order list would be a lie.
        $this->assertStringContainsString('/api/', $worker);
        $this->assertStringContainsString('offline.html', $worker);
        $this->assertStringContainsString('navigate', $worker);
        $this->assertStringContainsString('Tidak ada koneksi', $offline);
    }

    public function test_every_icon_the_manifests_reference_exists(): void
    {
        foreach ((array) config('pwa.apps', []) as $key => $app) {
            foreach ((array) ($app['icons'] ?? []) as $icon) {
                $path = public_path(ltrim((string) $icon['src'], '/'));

                $this->assertFileExists($path, "Missing icon for {$key}: {$icon['src']}");
            }

            $this->assertFileExists(public_path("icons/{$key}-apple-180.png"));
        }
    }

    public function test_the_retired_native_map_bridges_are_gone(): void
    {
        // The webview bridges the native shells embedded are gone with them:
        // their routes are unregistered, and the old URLs now fall through to
        // the SPA shell instead of answering with a map payload.
        $this->assertFalse(Route::has('courier.map'));
        $this->assertFalse(Route::has('courier.map.fix'));
        $this->assertFalse(Route::has('client.map.pick'));

        $this->get('/courier/map/AJ-FRZ-0012345/fix')
            ->assertOk()
            ->assertSee('data-app="courier"', false);

        $this->get('/client/map/pick')
            ->assertOk()
            ->assertSee('data-app="client"', false);
    }
}
