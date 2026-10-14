<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The app's entry point: send a signed-in courier to the Tugas tab and
 * everyone else to the login screen.
 */
final class Start extends NativeComponent {
    public function mount(): void {
        $this->navigate(
            MobileToken::get(MobileToken::COURIER) !== null ? '/courier/tasks' : '/courier/login'
        );
    }

    public function render(): Element {
        return $this->view('start', ['label' => 'Menyiapkan aplikasi…']);
    }
}
