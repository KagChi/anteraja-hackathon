<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientCart;
use App\Support\ClientPayload;
use App\Support\Concerns\LoadsFromApi;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * One catalog product: what it is, how it has to be handled, and how many the
 * customer wants. Adding to the basket is the only mutation on this screen.
 */
final class ProductDetail extends NativeComponent {
    use LoadsFromApi;

    public string $slug = '';

    /** @var array<string, mixed>|null */
    public ?array $product = null;

    public int $quantity = 1;

    public int $cartCount = 0;

    public bool $justAdded = false;

    public function mount(): void {
        $this->slug = (string) ($this->param('slug') ?? '');
        $this->cartCount = ClientCart::count();
        $this->load();
    }

    protected function load(): void {
        if ($this->slug === '') {
            $this->product = null;

            return;
        }

        $result = MobileApi::client()->get('/client/catalog/'.rawurlencode($this->slug));

        if (! $result->ok) {
            $this->product = null;

            // A missing product is an empty screen, not an error banner.
            if ($result->status !== 404) {
                $this->error = $result->message;
            }

            return;
        }

        $payload = $result->get('data');
        $this->product = ClientPayload::product(is_array($payload) ? $payload : []);
    }

    public function increment(): void {
        $this->justAdded = false;
        $this->quantity = min(ClientCart::MAX_QUANTITY, $this->quantity + 1);
    }

    public function decrement(): void {
        $this->justAdded = false;
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function addToCart(): void {
        if ($this->product === null) {
            return;
        }

        ClientCart::add((int) $this->product['id'], $this->quantity);
        $this->cartCount = ClientCart::count();
        $this->justAdded = true;
        $this->actionError = null;
    }

    public function openCart(): void {
        if ($this->cartCount === 0) {
            $this->actionError = 'Keranjang Anda masih kosong.';

            return;
        }

        $this->navigate('/client/checkout');
    }

    public function openCatalog(): void {
        $this->navigate('/client/catalog');
    }

    public function render(): Element {
        return $this->view('client.product-detail', [
            'product' => $this->product,
            'quantity' => $this->quantity,
            'cartCount' => $this->cartCount,
            'justAdded' => $this->justAdded,
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
