<?php

/*
|--------------------------------------------------------------------------
| Mobile App Registry
|--------------------------------------------------------------------------
|
| The single source of truth for the two Android shells built from this one
| codebase. `scripts/build-apk.sh <app>` reads it to stamp the per-app .env
| (package id, display name, start path) before the NativePHP build runs, and
| `config/mobile.php` reads it at runtime so the native shell knows which URL
| to load in its web view.
|
| Both apps render the same deployed site; only the entry screen differs:
| the courier lands on the task list, the customer on their orders.
|
| Keep these keys in step with the committed launcher art in mobile/icons/.
|
*/

return [
    'kurir' => [
        'app_id' => 'com.anteraja.kurir',
        'name' => 'Anteraja Kurir',
        'path' => '/courier/tasks',
    ],

    'client' => [
        'app_id' => 'com.anteraja.frozen',
        'name' => 'Anteraja Frozen',
        'path' => '/client/orders',
    ],
];
