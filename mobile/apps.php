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
| Jetpack Compose. Nothing is loaded over the network, so there is no site URL
| to configure and no web view involved.
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
        'start' => '/courier/tasks',
    ],

    'client' => [
        'app_id' => 'com.anteraja.frozen',
        'name' => 'Anteraja Frozen',
        'start' => '/client/orders',
    ],
];
