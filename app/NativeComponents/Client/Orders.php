<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ClientPayload;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The customer's live shipments — the app's home screen.
 *
 * One card per parcel in flight, read from GET /api/client/orders?tab=active.
 * Tapping a card opens its tracking screen.
 */
final class Orders extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $orders = [];

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::client()->get('/client/orders', ['tab' => 'active']);

        if (! $result->ok) {
            $this->error = $result->message;
            $this->orders = [];

            return;
        }

        $this->orders = array_values(array_map(
            static fn (array $order): array => ClientPayload::order($order),
            array_filter($result->data(), 'is_array'),
        ));
    }

    public function render(): Element {
        return $this->view('client.orders', [
            'orders' => $this->orders,
            'error' => $this->error,
        ]);
    }
}
