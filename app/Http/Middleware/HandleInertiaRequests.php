<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // The landing page's app cards read this. Identity and copy come
            // from config/pwa.php, the same registry the manifests are built
            // from, so the cards cannot drift from the apps they link to.
            'apps' => (static function (): array {
                $cards = [];

                foreach ((array) config('pwa.apps', []) as $key => $app) {
                    $card = is_array($app['card'] ?? null) ? $app['card'] : [];

                    $cards[] = [
                        'key' => (string) $key,
                        'name' => (string) ($app['name'] ?? ''),
                        'url' => (string) ($app['start_url'] ?? ''),
                        'audience' => (string) ($card['audience'] ?? 'Aplikasi'),
                        'description' => (string) ($card['description'] ?? ''),
                        'icon' => (string) ($card['icon'] ?? 'apps'),
                        'points' => array_values((array) ($card['points'] ?? [])),
                    ];
                }

                return $cards;
            })(),
        ]);
    }
}
