<?php

namespace Tests\Feature;

use App\NativeComponents\AppShell;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

/**
 * The Android shells render one native screen: a full-screen web view onto the
 * deployed site, or a notice when the build was stamped without one.
 *
 * The web view element comes from the nativephp/mobile-ui plugin, which has to
 * be registered in App\Providers\NativeServiceProvider — without that,
 * <native:webview> resolves to nothing and the screen dies at render time.
 */
class MobileShellTest extends TestCase {
    public function test_native_screen_shows_the_web_view_when_configured(): void {
        config(['mobile.start_url' => 'https://anteraja.example.com/courier/tasks']);

        Native::test(AppShell::class)
            ->assertElement('webview')
            ->assertDontSee('no site to show yet');
    }

    public function test_native_screen_explains_an_unconfigured_build(): void {
        config(['mobile.start_url' => '']);

        Native::test(AppShell::class)
            ->assertSee('no site to show yet')
            ->assertMissingElement('webview');
    }
}
