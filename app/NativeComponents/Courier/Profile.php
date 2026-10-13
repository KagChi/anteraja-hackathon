<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\CourierData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Courier identity, today's numbers, and the day's assignment.
 */
final class Profile extends NativeComponent {
    public function render(): Element {
        return $this->view('courier.profile', [
            'profile' => CourierData::profile(),
        ]);
    }
}
