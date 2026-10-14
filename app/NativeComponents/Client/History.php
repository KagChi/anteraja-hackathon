<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ClientPayload;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Parcels that already arrived — the Riwayat tab, read from
 * GET /api/client/orders?tab=completed.
 */
final class History extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $orders = [];

    public string $onTime = '96%';

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::client()->get('/client/orders', ['tab' => 'completed']);

        if (! $result->ok) {
            $this->error = $result->message;
            $this->orders = [];

            return;
        }

        $this->orders = array_values(array_map(
            static fn (array $order): array => ClientPayload::order($order),
            array_filter($result->data(), 'is_array'),
        ));

        $meta = $result->get('meta');
        if (is_array($meta) && is_string($meta['sla_rate'] ?? null) && $meta['sla_rate'] !== '') {
            $this->onTime = $meta['sla_rate'];
        }
    }

    public function render(): Element {
        return $this->view('client.history', [
            'orders' => $this->orders,
            'onTime' => $this->onTime,
            'error' => $this->error,
        ]);
    }
}
