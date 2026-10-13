<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\CourierData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Completed deliveries for the day. Read-only, so the screen is a summary
 * card plus one card per shipment.
 */
final class History extends NativeComponent {
    public function render(): Element {
        return $this->view('courier.history', [
            'completed' => CourierData::history(),
            'onTime' => '97%',
        ]);
    }
}
