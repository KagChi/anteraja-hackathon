<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Appearance;
use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Customer identity, lifetime numbers, the one channel out — customer service
 * on WhatsApp — and the app-wide theme switcher.
 */
final class Profile extends NativeComponent {
    /** Selected index into Appearance::modes() — the switcher's bound value. */
    public int $appearance = 0;

    public function mount(): void {
        $this->appearance = Appearance::index();
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink(ClientData::helpMessage()));
    }

    /** Pin the app palette; the next frame draws in it. */
    public function setAppearance(int $index): void {
        Appearance::select($index);
        $this->appearance = Appearance::index();
    }

    public function render(): Element {
        return $this->view('client.profile', [
            'profile' => ClientData::profile(),
            'support' => ClientData::support(),
            'appearance' => $this->appearance,
            'appearanceOptions' => Appearance::labels(),
        ]);
    }
}
