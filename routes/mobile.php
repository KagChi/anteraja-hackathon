<?php

use App\NativeComponents\Courier\Delivery;
use App\NativeComponents\Courier\History;
use App\NativeComponents\Courier\Issue;
use App\NativeComponents\Courier\Profile;
use App\NativeComponents\Courier\Proof;
use App\NativeComponents\Courier\Tasks;
use App\NativeComponents\Layouts\CourierTabsLayout;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Native (mobile shell) Routes
|--------------------------------------------------------------------------
|
| nativephp/mobile loads this file itself, so there is nothing to register in
| bootstrap/app.php. Both Android builds start at the first tab and are
| identical from there on: every screen is a NativeComponent rendered as
| native SwiftUI / Jetpack Compose UI, so there is no site URL to configure
| and nothing is loaded over the network.
|
| The three tab destinations share CourierTabsLayout; the pushed flow screens
| sit outside the group and carry their own back-navigable top bar.
|
*/

Route::nativeGroup(CourierTabsLayout::class, function () {
    Route::native('/courier/tasks', Tasks::class);
    Route::native('/courier/history', History::class);
    Route::native('/courier/profile', Profile::class);
});

Route::native('/courier/delivery/{resi}', Delivery::class);
Route::native('/courier/proof/{resi}', Proof::class);
Route::native('/courier/issue/{resi}', Issue::class);

Route::native('/courier', Tasks::class);
