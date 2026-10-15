<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ClientData;
use App\Support\ClientPayload;
use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Customer identity, lifetime numbers, the one channel out — customer service
 * on WhatsApp. Identity and stats come from GET /api/client/me.
 */
final class Profile extends NativeComponent {
    use LoadsFromApi;

    /** @var array<string, mixed> */
    public array $profile = [];

    /** @var array<string, mixed> */
    public array $support = [];

    public string $supportUrl = '';

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::client()->get('/client/me');

        if (! $result->ok) {
            $this->error = $result->message;
            $this->profile = ClientPayload::profile([]);
            $this->support = ['label' => 'Layanan Pelanggan Anteraja Frozen'];

            return;
        }

        $data = $result->data();
        $this->profile = ClientPayload::profile($data);

        $support = is_array($data['support'] ?? null) ? $data['support'] : [];
        $this->support = ['label' => 'Layanan Pelanggan Anteraja Frozen'];
        $this->supportUrl = is_string($support['whatsapp_url'] ?? null) && $support['whatsapp_url'] !== ''
            ? $support['whatsapp_url']
            : ClientData::whatsappLink(ClientData::helpMessage());
    }

    public function openSupport(): void {
        Browser::open($this->supportUrl !== '' ? $this->supportUrl : ClientData::whatsappLink(ClientData::helpMessage()));
    }

    public function logout(): void {
        MobileApi::client()->post('/client/auth/logout');
        MobileToken::clear(MobileToken::CLIENT);

        $this->navigate('/client/login');
    }

    public function render(): Element {
        return $this->view('client.profile', [
            'profile' => $this->profile,
            'support' => $this->support,
            'error' => $this->error,
        ]);
    }
}
