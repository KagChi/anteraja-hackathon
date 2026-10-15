<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ApiResult;
use App\Support\CourierPayload;
use App\Support\GeoDistance;
use App\Support\MobileApi;
use App\Http\Controllers\Courier\MapController;
use App\Services\Gps\FakeGpsDetector;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Geolocation\LocationReceived;
use Native\Mobile\Events\Geolocation\LocationUpdated;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Geolocation;
use Illuminate\Support\Facades\Cache;
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

    public ?float $accuracy = null;

    public ?int $deviceTimestamp = null;

    /**
     * The bounded fix window the server assesses (FRD-06 heuristics).
     *
     * @var list<array{latitude: float, longitude: float, accuracy: float|null, timestamp: int|null}>
     */
    public array $fixWindow = [];

    public string $gpsNotice = '';

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
        $this->rememberMapContext();
    }

    /**
     * Seed the map webview's cache with the destination and geofence, so the
     * embedded Leaflet page can draw something before the first GPS fix lands.
     */
    private function rememberMapContext(): void {
        $gps = $this->delivery['gps'] ?? [];
        $context = $this->mapContext();

        $context['destination_latitude'] = $gps['destination_latitude'] ?? null;
        $context['destination_longitude'] = $gps['destination_longitude'] ?? null;
        $context['radius_m'] = (int) ($gps['radius_m'] ?? 100);

        $this->putMapContext($context);
    }

    /** Push the latest device fix into the cache the map page polls. */
    private function cacheFix(): void {
        if ($this->resi === '' || $this->latitude === null || $this->longitude === null) {
            return;
        }

        $context = $this->mapContext();
        $context['latitude'] = $this->latitude;
        $context['longitude'] = $this->longitude;
        $context['accuracy_m'] = $this->accuracy;
        $context['at'] = now()->toIso8601String();

        $this->putMapContext($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapContext(): array {
        $existing = Cache::get(MapController::cacheKey($this->resi));

        return is_array($existing) ? $existing : [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function putMapContext(array $context): void {
        if ($this->resi === '') {
            return;
        }

        Cache::put(MapController::cacheKey($this->resi), $context, now()->addMinutes(30));
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
            // A stream rather than a single fix: the fix window is what the
            // server needs to tell a real journey from a simulated one.
            Geolocation::watchPosition(fineAccuracy: true)->minDistance(5)->start();
        } catch (Throwable) {
            // Best-effort; arriving still works without a fix.
        }
    }

    #[On(LocationUpdated::class)]
    public function onLocationUpdated(
        bool $success,
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $accuracy = null,
        ?int $timestamp = null,
    ): void {
        if (! $success || $latitude === null || $longitude === null) {
            return;
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->accuracy = $accuracy;
        $this->deviceTimestamp = $timestamp ?? (int) round(microtime(true) * 1000);

        $this->fixWindow[] = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'timestamp' => $this->deviceTimestamp,
        ];

        if (count($this->fixWindow) > FakeGpsDetector::MAX_FIX_WINDOW) {
            $this->fixWindow = array_slice($this->fixWindow, -FakeGpsDetector::MAX_FIX_WINDOW);
        }

        $this->cacheFix();
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

        $this->actionError = null;

        $result = MobileApi::courier()->post(
            '/courier/deliveries/'.rawurlencode($this->resi).'/arrive',
            $this->geoPayload(),
        );

        if (! $result->ok) {
            $this->actionError = $this->failureMessage($result);

            return;
        }

        // Re-read first (the GPS card may have new evidence to show), then pin
        // the local step: the delivery payload still reports the last recorded
        // milestone, which lags the write we just made.
        $this->load();
        $this->arrived = true;
    }

    /** Third step of the strip, first tap: leaving the hub with the parcel. */
    public function markDepart(): void {
        if ($this->delivery === null) {
            return;
        }

        $this->actionError = null;

        // The pre-departure physical check has no UI in the app yet, so the
        // courier ships an empty checklist (no findings) and the hub console
        // keeps the detailed form.
        $result = MobileApi::courier()->post(
            '/courier/deliveries/'.rawurlencode($this->resi).'/depart',
            $this->geoPayload() + ['checklist' => []],
        );

        if (! $result->ok) {
            $this->actionError = $this->failureMessage($result);

            return;
        }

        $this->load();
    }

    /**
     * Ask the hub to review the GPS verdict that stopped this step. The
     * incident stays on record; approval is what unlocks the retry.
     */
    public function requestReview(): void {
        if ($this->delivery === null) {
            return;
        }

        $this->actionError = null;
        $action = (string) ($this->delivery['gps']['action'] ?? 'POD');

        $result = MobileApi::courier()->post(
            '/courier/deliveries/'.rawurlencode($this->resi).'/gps-review',
            ['action' => $action],
        );

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->load();
        $this->actionError = null;
        $this->gpsNotice = 'Permintaan tinjauan terkirim ke hub.';
    }

    /**
     * Everything the server needs to judge this action: the current fix, the
     * window behind it, and the device clock.
     *
     * @return array<string, mixed>
     */
    private function geoPayload(): array {
        if ($this->latitude === null || $this->longitude === null) {
            return [];
        }

        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'device_timestamp' => $this->deviceTimestamp,
            'gps_signals' => $this->fixWindow,
        ];
    }

    /** A blocked action explains itself: the server sends the reasons with it. */
    private function failureMessage(ApiResult $result): string {
        $reasons = $result->raw['reasons'] ?? null;

        if (is_array($reasons) && isset($reasons[0]) && is_array($reasons[0])) {
            $label = (string) ($reasons[0]['label'] ?? '');

            if ($label !== '') {
                return $result->message.' '.$label.'.';
            }
        }

        return $result->message;
    }

    /**
     * Live distance to the destination, for the GPS card. The server re-derives
     * its own number; this is what the courier reads while walking.
     */
    public function distanceLabel(): ?string {
        $destination = $this->delivery['gps'] ?? null;

        if (! is_array($destination) || $this->latitude === null || $this->longitude === null) {
            return null;
        }

        $lat = $destination['destination_latitude'] ?? null;
        $lng = $destination['destination_longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $km = GeoDistance::haversine($this->latitude, $this->longitude, (float) $lat, (float) $lng);

        if ($km < 1) {
            return round($km * 1000).' m dari titik penerima';
        }

        return rtrim(rtrim(number_format($km, 1, ',', '.'), '0'), ',').' km dari titik penerima';
    }

    public function accuracyLabel(): ?string {
        if ($this->accuracy === null) {
            return null;
        }

        return 'Akurasi sinyal ±'.round($this->accuracy).' m';
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
            'gpsNotice' => $this->gpsNotice,
            'gpsDistance' => $this->distanceLabel(),
            'gpsAccuracy' => $this->accuracyLabel(),
            'fixCount' => count($this->fixWindow),
            'gpsVariant' => $this->gpsVariant(),
            'gpsCanReview' => $this->gpsCanReview(),
            'mapUrl' => '/courier/map/'.rawurlencode($this->resi),
            'hasMap' => $this->hasMap(),
        ]);
    }

    /** The map only makes sense once the recipient point is known. */
    private function hasMap(): bool {
        $gps = $this->delivery['gps'] ?? null;

        return is_array($gps)
            && $gps['destination_latitude'] !== null
            && $gps['destination_longitude'] !== null;
    }

    /** Badge tone for the verdict: clean, flagged, or refused. */
    private function gpsVariant(): string {
        $level = (string) ($this->delivery['gps']['level'] ?? 'CLEAN');

        return match ($level) {
            'BLOCKED' => 'destructive',
            'SUSPECTED' => 'secondary',
            default => 'success',
        };
    }

    private function gpsCanReview(): bool {
        return (bool) ($this->delivery['gps']['can_request_review'] ?? false);
    }
}
