<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\ClientCart;
use App\Support\ClientPayload;
use App\Support\ClientReceipt;
use App\Support\Concerns\LoadsFromApi;
use App\Support\DisplayTime;
use App\Support\MobileApi;
use App\Support\MobileToken;
use Carbon\Carbon;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Checkout: what is being sent, when it should arrive, and to whom.
 *
 * The customer is the sender, so the address block belongs to the recipient and
 * starts blank: the customer's own profile is never copied into it. What is
 * typed here becomes the parcel's destination, the point the courier's geofence
 * is measured against, and the number the delivery updates go to.
 */
final class Checkout extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public int $totalIdr = 0;

    public string $totalLabel = 'Rp 0';

    public int $cartCount = 0;

    public string $recipientName = '';

    public string $recipientPhone = '';

    public string $recipientAddress = '';

    public string $recipientArea = '';

    public string $addressType = 'Rumah / Residensial';

    public string $serviceType = 'Reguler';

    public string $scheduleDate = '';

    public string $scheduleWindow = '13:00-15:00';

    public string $notes = '';

    public bool $busy = false;

    public function mount(): void {
        if (MobileToken::get(MobileToken::CLIENT) === null) {
            $this->navigate('/client/register');

            return;
        }

        $dates = $this->dateOptions();
        $this->scheduleDate = $dates[0] ?? $this->scheduleLabel(1);
        $this->scheduleWindow = $this->windowOptions()[2] ?? '13:00-15:00';

        $this->load();
    }

    /**
     * Reads the basket back through the catalog: product ids are the only thing
     * the cart stores, so a price change or a product that went off sale is
     * reflected here rather than quoted from a stale basket.
     */
    protected function load(): void {
        $cart = ClientCart::items();
        if ($cart === []) {
            $this->lines = [];
            $this->cartCount = 0;
            $this->totalIdr = 0;
            $this->totalLabel = 'Rp 0';

            return;
        }

        $result = MobileApi::client()->get('/client/catalog');
        if (! $result->ok) {
            $this->error = $result->message;

            return;
        }

        $lines = [];
        $total = 0;
        $count = 0;

        foreach (array_filter($result->data(), 'is_array') as $row) {
            $product = ClientPayload::product($row);
            $id = (int) $product['id'];

            if (! isset($cart[$id])) {
                continue;
            }

            $quantity = (int) $cart[$id];
            $lineTotal = $product['price_idr'] * $quantity;

            $total += $lineTotal;
            $count += $quantity;

            $lines[] = [
                'id' => $id,
                'name' => $product['name'],
                'category' => $product['category'],
                'price_label' => $product['price_label'],
                'weight_label' => $product['weight_label'],
                'quantity' => $quantity,
                'line_total_label' => 'Rp '.number_format($lineTotal, 0, ',', '.'),
            ];
        }

        // Anything the catalog no longer offers is dropped from the basket, so
        // the count on this screen always matches what can actually be ordered.
        $available = array_column($lines, 'id');
        foreach (array_keys($cart) as $productId) {
            if (! in_array($productId, $available, true)) {
                ClientCart::remove((int) $productId);
            }
        }

        $this->lines = $lines;
        $this->cartCount = $count;
        $this->totalIdr = $total;
        $this->totalLabel = 'Rp '.number_format($total, 0, ',', '.');
    }

    public function increment(int $productId): void {
        ClientCart::add($productId);
        $this->actionError = null;
        $this->load();
    }

    public function decrement(int $productId): void {
        $quantity = ClientCart::quantity($productId);

        if ($quantity <= 1) {
            ClientCart::remove($productId);
        } else {
            ClientCart::set($productId, $quantity - 1);
        }

        $this->actionError = null;
        $this->load();
    }

    public function removeLine(int $productId): void {
        ClientCart::remove($productId);
        $this->load();
    }

    public function submit(): void {
        if ($this->busy) {
            return;
        }

        $this->error = null;
        $this->actionError = null;

        if ($this->lines === []) {
            $this->actionError = 'Keranjang masih kosong. Tambahkan produk dulu.';

            return;
        }

        if (trim($this->recipientName) === '' || trim($this->recipientPhone) === '' || trim($this->recipientAddress) === '') {
            $this->actionError = 'Nama penerima, nomor HP, dan alamat wajib diisi.';

            return;
        }

        [$windowStart, $windowEnd] = $this->windowBounds();

        $items = array_map(
            static fn (array $line): array => ['product_id' => $line['id'], 'quantity' => $line['quantity']],
            $this->lines,
        );

        $this->busy = true;
        $result = MobileApi::client()->post('/client/orders', [
            'recipient' => [
                'name' => trim($this->recipientName),
                'phone' => trim($this->recipientPhone),
                'address' => trim($this->recipientAddress),
                'area' => trim($this->recipientArea) !== '' ? trim($this->recipientArea) : null,
                'address_type' => str_contains($this->addressType, 'Kantor') ? 'COMMERCIAL' : 'RESIDENTIAL',
            ],
            'service_type' => str_contains($this->serviceType, 'Instant') ? 'INSTANT_FROZEN' : 'REGULER',
            'scheduled_date' => $this->scheduledDateIso(),
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'handling_instructions' => trim($this->notes) !== '' ? trim($this->notes) : null,
            'items' => $items,
        ]);
        $this->busy = false;

        if (! $result->ok) {
            $this->actionError = $result->firstError() ?? $result->message;

            return;
        }

        $receipt = ClientPayload::receipt($result->data());
        $resi = (string) $receipt['resi'];

        if ($resi === '') {
            $this->actionError = 'Pesanan dibuat, tetapi nomor resi tidak diterima. Cek tab Pesanan.';

            return;
        }

        ClientReceipt::put($receipt);
        ClientCart::clear();

        $this->navigate('/client/order-success?resi='.rawurlencode($resi));
    }

    public function openCatalog(): void {
        $this->navigate('/client/catalog');
    }

    /**
     * @return list<string>
     */
    public function serviceOptions(): array {
        return ['Reguler', 'Instant Frozen'];
    }

    /**
     * The next four delivery days, labelled the way the app speaks.
     *
     * @return list<string>
     */
    public function dateOptions(): array {
        $labels = [];

        for ($offset = 1; $offset <= 4; $offset++) {
            $labels[] = $this->scheduleLabel($offset);
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function windowOptions(): array {
        $slots = config('coldchain.client.slots', []);

        return is_array($slots) && $slots !== [] ? array_values(array_map('strval', $slots)) : ['09:00-11:00', '13:00-15:00'];
    }

    /**
     * @return list<string>
     */
    public function addressTypeOptions(): array {
        return ['Rumah / Residensial', 'Kantor / Komersial'];
    }

    private function scheduleLabel(int $offsetDays): string {
        $date = Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->addDays($offsetDays);
        $prefix = $offsetDays === 1 ? 'Besok' : $date->locale('id')->translatedFormat('l');

        return $prefix.', '.$date->locale('id')->translatedFormat('j M');
    }

    /** The label the picker holds is turned back into an ISO date for the API. */
    private function scheduledDateIso(): string {
        $index = array_search($this->scheduleDate, $this->dateOptions(), true);
        $offset = $index === false ? 1 : ((int) $index) + 1;

        return Carbon::now(DisplayTime::DISPLAY_TIMEZONE)->addDays($offset)->toDateString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function windowBounds(): array {
        $parts = explode('-', $this->scheduleWindow);

        if (count($parts) !== 2) {
            return ['13:00', '15:00'];
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    public function render(): Element {
        return $this->view('client.checkout', [
            'lines' => $this->lines,
            'cartCount' => $this->cartCount,
            'totalLabel' => $this->totalLabel,
            'busy' => $this->busy,
            'serviceOptions' => $this->serviceOptions(),
            'dateOptions' => $this->dateOptions(),
            'windowOptions' => $this->windowOptions(),
            'typeOptions' => $this->addressTypeOptions(),
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
