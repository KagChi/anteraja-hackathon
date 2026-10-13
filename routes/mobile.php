<?php

use App\NativeComponents\AppShell;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Native (mobile shell) Routes
|--------------------------------------------------------------------------
|
| nativephp/mobile loads this file itself, so there is nothing to register in
| bootstrap/app.php. Both Android builds start at /shell (NATIVEPHP_START_URL)
| and are identical from there on: the screen is a single full-screen web view
| whose URL comes from config/mobile.php.
|
*/

Route::native('/shell', AppShell::class);
