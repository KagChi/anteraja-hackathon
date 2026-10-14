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
 * The frozen-goods catalog.
 *
 * The list comes straight from GET /api/client/catalog, so the price and weight
 * on a card are the ones the server will charge. Adding to the basket only
 * records product ids; the checkout screen re-reads the catalog for names and
 * prices.
 */
final class Catalog extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $products = [];

    public int $cartCount = 0;

    /** Slug of the last product added, so the banner can name it. */
    public string $addedName = '';

    public function mount(): void {
        $this->cartCount = ClientCart::count();
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::client()->get('/client/catalog');

        if (! $result->ok) {
            $this->products = [];
            $this->error = $result->message;

            return;
        }

        $rows = array_values(array_filter($result->data(), 'is_array'));
        $this->products = array_map(
            static fn (array $product): array => ClientPayload::product($product),
            $rows,
        );
    }

    public function addToCart(int $productId): void {
        ClientCart::add($productId);
        $this->cartCount = ClientCart::count();
        $this->addedName = $this->nameFor($productId);
        $this->actionError = null;
    }

    public function openCheckout(): void {
        if ($this->cartCount === 0) {
            $this->actionError = 'Pilih minimal satu produk sebelum checkout.';

            return;
        }

        $this->navigate('/client/checkout');
    }

    public function clearCart(): void {
        ClientCart::clear();
        $this->cartCount = 0;
        $this->addedName = '';
    }

    private function nameFor(int $productId): string {
        foreach ($this->products as $product) {
            if ((int) $product['id'] === $productId) {
                return (string) $product['name'];
            }
        }

        return 'Produk';
    }

    public function render(): Element {
        return $this->view('client.catalog', [
            'products' => $this->products,
            'cartCount' => $this->cartCount,
            'addedName' => $this->addedName,
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
