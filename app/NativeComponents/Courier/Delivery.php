<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\CourierData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * The shipment currently on the road: where it goes, who receives it, and the
 * two ways out — hand it over, or report a problem.
 *
 * The web version embedded a decorative map. There is no native map element, so
 * the route opens in the system maps app instead, which is what the courier
 * wants from that button anyway.
 */
final class Delivery extends NativeComponent {
    public string $resi = '';

    public bool $arrived = false;

    public bool $sheetOpen = false;

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?: CourierData::activeDelivery()['tracking']);
    }

    /** Third step of the progress strip — tapped when the courier reaches the address. */
    public function markArrived(): void {
        $this->arrived = true;
    }

    public function openSheet(): void {
        $this->sheetOpen = true;
    }

    public function closeSheet(): void {
        $this->sheetOpen = false;
    }

    public function openRoute(): void {
        Browser::open(CourierData::activeDelivery()['map_href']);
    }

    public function callRecipient(): void {
        Browser::open(CourierData::activeDelivery()['phone_href']);
    }

    public function reportIssue(string $label): void {
        $this->sheetOpen = false;
        $this->navigate('/courier/issue/'.$this->resi.'?reason='.rawurlencode($label));
    }

    public function render(): Element {
        return $this->view('courier.delivery', [
            'delivery' => CourierData::activeDelivery(),
            'issues' => CourierData::issueSheetOptions(),
        ]);
    }
}
