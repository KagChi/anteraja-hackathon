<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ClientData;
use App\Support\ClientPayload;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Where one parcel is right now: the cold-chain reading, the courier who has
 * it, and the journey so far — GET /api/client/orders/{resi}/tracking.
 *
 * The resi is marked select-text instead of offering a copy button: the
 * system's own long-press copy is the native way to hand a tracking number
 * to another app, and there is no clipboard bridge in core.
 */
final class Tracking extends NativeComponent {
    use LoadsFromApi;

    /** @var array<string, mixed>|null */
    public ?array $order = null;

    public string $resi = '';

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->order = null;

            return;
        }

        $result = MobileApi::client()->get('/client/orders/'.rawurlencode($this->resi).'/tracking');

        if (! $result->ok) {
            $this->order = null;
            // A 404 is the API's "resi not on this account", which the screen
            // answers with its not-found card rather than a retry.
            if ($result->status !== 404) {
                $this->error = $result->message;
            }

            return;
        }

        $this->order = ClientPayload::trackingOrder($result->data());
    }

    public function callCourier(): void {
        if ($this->order === null) {
            return;
        }

        $phone = (string) ($this->order['courier_phone'] ?? '');
        if ($phone === '') {
            return;
        }

        Browser::open('tel:'.preg_replace('/[^0-9+]/', '', $phone));
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi.'.'));
    }

    public function openSchedule(): void {
        if ($this->order !== null) {
            $this->navigate('/client/schedule/'.$this->resi);
        }
    }

    public function openIssue(): void {
        if ($this->order !== null) {
            $this->navigate('/client/issue/'.$this->resi);
        }
    }

    public function render(): Element {
        return $this->view('client.tracking', [
            'order' => $this->order,
            'resi' => $this->resi,
            'error' => $this->error,
        ]);
    }
}
