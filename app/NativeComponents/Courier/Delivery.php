<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\CourierPayload;
use App\Support\MobileApi;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Geolocation\LocationReceived;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Geolocation;
use Throwable;

/**
 * The shipment currently on the road — GET /api/courier/deliveries/{resi}.
 *
 * The web version embedded a decorative map. There is no native map element,
 * so the route opens in the system maps app instead, with the link the API
 * hands back.
 */
final class Delivery extends NativeComponent {
    use LoadsFromApi;

    public string $resi = '';

    /** @var array<string, mixed>|null */
    public ?array $delivery = null;

    public bool $arrived = false;

    public bool $sheetOpen = false;

    /** @var list<string> */
    public array $issues = [];

    public ?float $latitude = null;

    public ?float $longitude = null;

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();
        $this->requestLocation();
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->delivery = null;

            return;
        }

        $result = MobileApi::courier()->get('/courier/deliveries/'.rawurlencode($this->resi));

        if (! $result->ok) {
            $this->delivery = null;
            if ($result->status !== 404) {
                $this->error = $result->message;
            }

            return;
        }

        $payload = $result->get('delivery');
        $this->delivery = CourierPayload::delivery(is_array($payload) ? $payload : []);
        $this->arrived = ($this->delivery['stage'] ?? '') === 'ARRIVED';
        $this->loadIssueOptions();
    }

    private function loadIssueOptions(): void {
        $result = MobileApi::courier()->get('/courier/deliveries/'.rawurlencode($this->resi).'/issue');

        if (! $result->ok) {
            return;
        }

        $form = $result->get('issueForm');
        $mapped = CourierPayload::issueForm(is_array($form) ? $form : []);

        $labels = [];
        foreach ($mapped['options'] as $option) {
            $label = (string) ($option['label'] ?? '');
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $this->issues = $labels;
    }

    private function requestLocation(): void {
        if (! function_exists('nativephp_call')) {
            return;
        }

        try {
            Geolocation::getCurrentPosition()->get();
        } catch (Throwable) {
            // Best-effort; arriving still works without a fix.
        }
    }

    #[On(LocationReceived::class)]
    public function onLocationReceived(bool $success, ?float $latitude = null, ?float $longitude = null): void {
        if (! $success) {
            return;
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /** Third step of the progress strip — tapped when the courier reaches the address. */
    public function markArrived(): void {
        if ($this->delivery === null) {
            return;
        }

        $payload = [];
        if ($this->latitude !== null && $this->longitude !== null) {
            $payload['latitude'] = $this->latitude;
            $payload['longitude'] = $this->longitude;
        }

        $result = MobileApi::courier()->post('/courier/deliveries/'.rawurlencode($this->resi).'/arrive', $payload);

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->arrived = true;
    }

    public function openSheet(): void {
        $this->sheetOpen = true;
    }

    public function closeSheet(): void {
        $this->sheetOpen = false;
    }

    public function openRoute(): void {
        $href = (string) ($this->delivery['map_href'] ?? '');
        if ($href !== '') {
            Browser::open($href);
        }
    }

    public function callRecipient(): void {
        $href = (string) ($this->delivery['phone_href'] ?? '');
        if ($href !== '') {
            Browser::open($href);
        }
    }

    public function reportIssue(string $label): void {
        $this->sheetOpen = false;
        $this->navigate('/courier/issue/'.$this->resi.'?reason='.rawurlencode($label));
    }

    public function render(): Element {
        return $this->view('courier.delivery', [
            'delivery' => $this->delivery,
            'issues' => $this->issues,
            'resi' => $this->resi,
            'arrived' => $this->arrived,
            'sheetOpen' => $this->sheetOpen,
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
