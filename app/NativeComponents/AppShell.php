<?php

namespace App\NativeComponents;

use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Element;

/**
 * The Android shell rendered on launch.
 *
 * There is no native UI to speak of: the whole screen is one full-screen web
 * view onto the deployed site, which keeps the courier and customer journeys
 * in a single implementation instead of a parallel native one. The only
 * branching here is the URL the web view loads — /courier/tasks for the
 * courier build, /client/orders for the customer build (config/mobile.php).
 */
class AppShell extends NativeComponent {
    public function render(): Element {
        // The view reads its URL straight from config, which keeps the whole
        // shell static. `$this->view()` takes the name without the `native.`
        // prefix, which is the form `native:validate` resolves — so the web
        // view tag in there gets checked for typos on every build.
        return $this->view('app-shell');
    }
}
