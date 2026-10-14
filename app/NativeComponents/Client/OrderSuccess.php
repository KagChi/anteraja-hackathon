<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientReceipt;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * What the customer sees straight after checkout: the resi, the agreed window,
 * and what is in the box.
 */
final class OrderSuccess extends NativeComponent {
    public string $resi = '';

    /** @var array<string, mixed>|null */
    public ?array $receipt = null;

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');

        $receipt = ClientReceipt::get();

        // Only show the stored receipt when it belongs to the resi on screen: a
        // stale one from an earlier order would name the wrong parcel.
        if ($receipt !== null && ($receipt['resi'] ?? '') === $this->resi) {
            $this->receipt = $receipt;
        }
    }

    public function openOrders(): void {
        $this->navigate('/client/orders');
    }

    public function openCatalog(): void {
        $this->navigate('/client/catalog');
    }

    public function render(): Element {
        return $this->view('client.order-success', [
            'resi' => $this->resi,
            'receipt' => $this->receipt,
        ]);
    }
}
