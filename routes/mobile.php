<?php

use App\NativeComponents\Courier\Delivery;
use App\NativeComponents\Courier\History;
use App\NativeComponents\Courier\Issue;
use App\NativeComponents\Courier\Profile;
use App\NativeComponents\Courier\Proof;
use App\NativeComponents\Courier\Tasks;
use App\NativeComponents\Layouts\ClientTabsLayout;
use App\NativeComponents\Layouts\CourierTabsLayout;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Native (mobile shell) Routes
|--------------------------------------------------------------------------
|
| nativephp/mobile loads this file itself, so there is nothing to register in
| bootstrap/app.php. Both Android builds are native UI and open on the first
| tab of their own section: every screen is a NativeComponent rendered as
| native SwiftUI / Jetpack Compose UI, so there is no site URL to configure
| and nothing is loaded over the network.
|
| Each app's three tab destinations share a layout (CourierTabsLayout for the
| courier, ClientTabsLayout for the customer); the pushed flow screens sit
| outside those groups and carry their own back-navigable top bar instead.
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

// The customer screens take their names from the web pages they port, so
// Client\History, Client\Profile and Client\Issue collide with the courier
// classes above. They are spelled out in full rather than imported under an
// alias because that is the form a route's component is resolved from
// outside the framework — by the APK build and by native:validate.
Route::nativeGroup(ClientTabsLayout::class, function () {
    Route::native('/client/orders', \App\NativeComponents\Client\Orders::class);
    Route::native('/client/history', \App\NativeComponents\Client\History::class);
    Route::native('/client/profile', \App\NativeComponents\Client\Profile::class);
});

Route::native('/client/tracking/{resi}', \App\NativeComponents\Client\Tracking::class);
Route::native('/client/schedule/{resi}', \App\NativeComponents\Client\Schedule::class);
Route::native('/client/issue/{resi}', \App\NativeComponents\Client\Issue::class);

Route::native('/client', \App\NativeComponents\Client\Orders::class);
