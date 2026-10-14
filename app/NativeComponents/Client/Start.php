<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The app's entry point: send a signed-in customer to the Pesanan tab and
 * everyone else to the landing screen, where they can look around before
 * deciding to sign up.
 */
final class Start extends NativeComponent {
    public function mount(): void {
        $this->navigate(
            MobileToken::get(MobileToken::CLIENT) !== null ? '/client/orders' : '/client/landing'
        );
    }

    public function render(): Element {
        return $this->view('start', ['label' => 'Menyiapkan aplikasi…']);
    }
}
