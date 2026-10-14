<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Customer identity, lifetime numbers, and the one channel out — customer
 * service on WhatsApp.
 */
final class Profile extends NativeComponent {
    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink(ClientData::helpMessage()));
    }

    public function render(): Element {
        return $this->view('client.profile', [
            'profile' => ClientData::profile(),
            'support' => ClientData::support(),
        ]);
    }
}
