<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * The customer's side of a cold-chain problem: what the hub found, what
 * operations decided, and the way to a replacement delivery.
 *
 * The decision already carries the resend, so the only action here is to ask
 * for a new window — which is the schedule screen.
 */
final class Issue extends NativeComponent {
    public ?array $order = null;

    public ?array $issue = null;

    public function mount(): void {
        $this->order = ClientData::find($this->resi());
        $this->issue = $this->order['issue'] ?? null;
    }

    public function requestResend(): void {
        if ($this->order === null) {
            return;
        }

        $this->navigate('/client/schedule/'.$this->order['resi']);
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi().'.'));
    }

    public function render(): Element {
        return $this->view('client.issue', [
            'order' => $this->order,
            'issue' => $this->issue,
            'resi' => $this->resi(),
        ]);
    }

    /** The resi in the route, or an empty string when the screen was mounted without one. */
    private function resi(): string {
        return (string) ($this->param('resi') ?? '');
    }
}
