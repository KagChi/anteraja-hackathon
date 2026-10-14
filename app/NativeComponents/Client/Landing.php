<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientCart;
use App\Support\ClientPayload;
use App\Support\Concerns\LoadsFromApi;
use App\Support\MobileApi;
use App\Support\MobileToken;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The customer app's landing screen, and the first tab.
 *
 * It is open to everyone: a visitor can read what the service does and look at
 * the catalog before signing up, which is why the sign-in buttons sit at the
 * end of the page rather than in front of it.
 */
final class Landing extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $featured = [];

    public bool $signedIn = false;

    public int $cartCount = 0;

    public function mount(): void {
        $this->signedIn = MobileToken::get(MobileToken::CLIENT) !== null;
        $this->cartCount = ClientCart::count();
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::client()->get('/client/catalog');

        if (! $result->ok) {
            $this->featured = [];
            $this->error = $result->message;

            return;
        }

        $products = array_values(array_filter($result->data(), 'is_array'));
        $mapped = array_map(static fn (array $product): array => ClientPayload::product($product), $products);

        $this->featured = array_slice($mapped, 0, 4);
    }

    public function render(): Element {
        return $this->view('client.landing', [
            'featured' => $this->featured,
            'signedIn' => $this->signedIn,
            'cartCount' => $this->cartCount,
            'error' => $this->error,
        ]);
    }

    /** Bottom of the page: the visitor already decided, send them to sign in. */
    public function goToLogin(): void {
        $this->navigate('/client/login');
    }
}
