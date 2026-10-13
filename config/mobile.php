<?php

/*
|--------------------------------------------------------------------------
| NativePHP Mobile Shell
|--------------------------------------------------------------------------
|
| The Android shells are thin wrappers: a native screen whose whole body is
| one full-screen web view onto the deployed site. Everything the courier or
| the customer sees is still rendered by that site, so adding the backend API
| later needs no new APK.
|
| `mobile/apps.php` holds the per-app identity map; MOBILE_WEB_BASE_URL is the
| deployed origin and is stamped into each build. The build script refuses to
| package an app without it, so an empty value here means the shell was built
| by hand — the native screen renders a placeholder instead of a dead web view.
|
*/

$apps = require __DIR__ . '/../mobile/apps.php';

$app = (string) env('MOBILE_APP', 'kurir');
$baseUrl = rtrim((string) env('MOBILE_WEB_BASE_URL', ''), '/');

return [
    /* Which entry in the app map this build is stamped with. */
    'app' => $app,

    /* The full identity map, keyed by MOBILE_APP value. */
    'apps' => $apps,

    /* Origin of the deployed site, e.g. https://anteraja.example.com. */
    'base_url' => $baseUrl,

    /* Absolute URL the shell's web view loads. Empty when unconfigured. */
    'start_url' => $baseUrl === '' ? '' : $baseUrl . ($apps[$app]['path'] ?? '/'),
];
