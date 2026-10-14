<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The customer's live shipments — the app's home screen.
 *
 * One card per parcel in flight; tapping a card opens its tracking screen.
 * Nothing on this screen carries state between renders, so it is a straight
 * read of the fixtures.
 */
final class Orders extends NativeComponent {
    public function render(): Element {
        return $this->view('client.orders', [
            'orders' => ClientData::activeOrders(),
        ]);
    }
}
