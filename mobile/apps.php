<?php

/*
|--------------------------------------------------------------------------
| Mobile App Registry
|--------------------------------------------------------------------------
|
| The single source of truth for the Android shells built from this one
| codebase. `scripts/build-apk.sh <app>` reads it to stamp the per-app .env
| (package id, display name, start route) before the NativePHP build runs.
|
| Both apps are native UI — NativeComponent screens rendered as SwiftUI and
| Jetpack Compose, reading the backend API over HTTPS with a bearer token. No
| web view is involved, so no site URL has to be configured for one.
|
| `start` is the native route the app opens on. It is null while an app's
| screens have not been ported yet; the build script refuses to package an app
| without one rather than ship a build that opens the wrong flow.
|
| Keep these keys in step with the committed launcher art in mobile/icons/.
|
*/

return [
    'kurir' => [
        'app_id' => 'com.anteraja.kurir',
        'name' => 'Anteraja Kurir',
        'start' => '/courier/start',
    ],

    'client' => [
        'app_id' => 'com.anteraja.frozen',
        'name' => 'Anteraja Frozen',
        'start' => '/client/start',
    ],
];
