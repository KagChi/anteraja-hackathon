<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Parcels that already arrived. Read-only, so the screen is a summary card
 * plus one card per shipment.
 */
final class History extends NativeComponent {
    public function render(): Element {
        return $this->view('client.history', [
            'orders' => ClientData::pastOrders(),
            'onTime' => '96%',
        ]);
    }
}
