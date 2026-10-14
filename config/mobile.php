<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Backend API
    |--------------------------------------------------------------------------
    |
    | Where the native shells send their requests. The apps talk to the same
    | Laravel backend the web console does, over HTTPS, with a Sanctum bearer
    | token. Stamp a different host per build with MOBILE_API_BASE_URL; the
    | default is the hackathon deployment.
    |
    */

    'api_base_url' => rtrim((string) env('MOBILE_API_BASE_URL', 'https://hackathon.kagchi.my.id/api'), '/'),

    'timeout' => (int) env('MOBILE_API_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Download links
    |--------------------------------------------------------------------------
    |
    | The landing page offers both Android builds. Identity comes from
    | mobile/apps.php, which is the same registry the APK build script reads, so
    | a renamed app cannot drift from the marketing page. Only the download URL
    | is environment-specific: CI attaches the signed APKs to a GitHub release,
    | and hosting them elsewhere is an env change, not a code change.
    |
    */

    'apps' => (static function (): array {
        /** @var array<string, array{app_id: string, name: string, start: ?string}> $apps */
        $apps = require base_path('mobile/apps.php');

        $defaultUrl = env('ANDROID_APK_URL', 'https://github.com/KagChi/anteraja-hackathon/releases/latest');
        $version = env('ANDROID_APK_VERSION', 'Rilis terbaru');

        $links = [];

        foreach ($apps as $key => $app) {
            $envKey = $key === 'kurir' ? 'ANDROID_KURIR_APK_URL' : 'ANDROID_CLIENT_APK_URL';

            $links[$key] = [
                'key' => $key,
                'name' => $app['name'],
                'app_id' => $app['app_id'],
                'apk_url' => env($envKey, $defaultUrl),
                'version' => $version,
            ];
        }

        return $links;
    })(),
];
