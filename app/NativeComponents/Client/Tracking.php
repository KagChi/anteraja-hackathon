<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Where one parcel is right now: the cold-chain reading, the courier who has
 * it, and the journey so far.
 *
 * The resi is marked select-text instead of offering a copy button: the
 * system's own long-press copy is the native way to hand a tracking number
 * to another app, and there is no clipboard bridge in core.
 */
final class Tracking extends NativeComponent {
    public ?array $order = null;

    public function mount(): void {
        $this->order = ClientData::find($this->resi());
    }

    public function callCourier(): void {
        if ($this->order === null) {
            return;
        }

        Browser::open($this->order['courier_tel']);
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi().'.'));
    }

    public function openSchedule(): void {
        if ($this->order !== null) {
            $this->navigate('/client/schedule/'.$this->order['resi']);
        }
    }

    public function openIssue(): void {
        if ($this->order !== null) {
            $this->navigate('/client/issue/'.$this->order['resi']);
        }
    }

    public function render(): Element {
        return $this->view('client.tracking', [
            'order' => $this->order,
            'resi' => $this->resi(),
        ]);
    }

    /** The resi in the route, or an empty string when the screen was mounted without one. */
    private function resi(): string {
        return (string) ($this->param('resi') ?? '');
    }
}
