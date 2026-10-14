<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * The delivery window the customer is asked to accept, and the two ways out:
 * confirm it, or ask for another one.
 *
 * Confirmation is local to the prototype — the token API's
 * /schedule/confirm call is what this seam becomes once the flow is wired.
 */
final class Schedule extends NativeComponent {
    /** idle | confirmed */
    public string $state = 'idle';

    public ?array $order = null;

    public ?array $schedule = null;

    public function mount(): void {
        $this->order = ClientData::find($this->resi());
        $this->schedule = $this->order['schedule'] ?? null;
    }

    public function confirm(): void {
        if ($this->state !== 'idle' || $this->schedule === null) {
            return;
        }

        $this->state = 'confirmed';
    }

    public function requestOtherWindow(): void {
        Browser::open(ClientData::whatsappLink(
            'Saya ingin meminta jadwal pengantaran lain untuk resi '.$this->resi().'.'
        ));
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi().'.'));
    }

    public function render(): Element {
        return $this->view('client.schedule', [
            'order' => $this->order,
            'schedule' => $this->schedule,
            'resi' => $this->resi(),
        ]);
    }

    /** The resi in the route, or an empty string when the screen was mounted without one. */
    private function resi(): string {
        return (string) ($this->param('resi') ?? '');
    }
}
