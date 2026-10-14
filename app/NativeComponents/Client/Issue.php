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
 * The customer's side of a cold-chain problem: what the hub found, what
 * operations decided, and the way to a replacement delivery — GET
 * /api/client/orders/{resi}/issue, POST .../issue/resend.
 */
final class Issue extends NativeComponent {
    use LoadsFromApi;

    /** @var array<string, mixed>|null */
    public ?array $order = null;

    /** @var array<string, mixed>|null */
    public ?array $issue = null;

    public string $resi = '';

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->order = null;
            $this->issue = null;

            return;
        }

        $result = MobileApi::client()->get('/client/orders/'.rawurlencode($this->resi).'/issue');

        if (! $result->ok) {
            $this->order = null;
            $this->issue = null;
            if ($result->status !== 404) {
                $this->error = $result->message;
            }

            return;
        }

        // The API answers with a bare redirect_url when the parcel has no
        // issue; without a data block there is nothing for this screen.
        if (! array_key_exists('data', $result->raw)) {
            $this->order = null;
            $this->issue = null;

            return;
        }

        $data = $result->data();
        $this->order = ClientPayload::orderCard($data);
        $this->issue = ClientPayload::issue($data);
    }

    public function requestResend(): void {
        if ($this->order === null) {
            return;
        }

        $result = MobileApi::client()->post('/client/orders/'.rawurlencode($this->resi).'/issue/resend');

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $replacement = $result->data()['replacement_tracking_number'] ?? null;
        $target = is_string($replacement) && $replacement !== '' ? $replacement : $this->resi;

        $this->navigate('/client/schedule/'.$target);
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi.'.'));
    }

    public function render(): Element {
        return $this->view('client.issue', [
            'order' => $this->order,
            'issue' => $this->issue,
            'resi' => $this->resi,
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
