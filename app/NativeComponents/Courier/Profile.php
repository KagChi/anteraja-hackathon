<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Appearance;
use App\Support\CourierData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Courier identity, today's numbers, the day's assignment, and the app-wide
 * theme switcher.
 */
final class Profile extends NativeComponent {
    /** Selected index into Appearance::modes() — the switcher's bound value. */
    public int $appearance = 0;

    public function mount(): void {
        $this->appearance = Appearance::index();
    }

    /** Pin the app palette; the next frame draws in it. */
    public function setAppearance(int $index): void {
        Appearance::select($index);
        $this->appearance = Appearance::index();
    }

    public function render(): Element {
        return $this->view('courier.profile', [
            'profile' => CourierData::profile(),
            'appearance' => $this->appearance,
            'appearanceOptions' => Appearance::labels(),
        ]);
    }
}
