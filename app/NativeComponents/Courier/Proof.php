<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ApiResult;
use App\Support\CourierPayload;
use App\Support\MobileApi;
use App\Services\Gps\FakeGpsDetector;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Geolocation\LocationUpdated;
use Native\Mobile\Facades\Camera;
use Native\Mobile\Facades\Geolocation;
use Throwable;

/**
 * Proof of delivery: who physically took the parcel, the photo that shows it,
 * and an optional note — POST /api/courier/deliveries/{resi}/proof.
 *
 * The photo comes from the device camera rather than a file input — the whole
 * reason this screen is native. Off-device the capture does nothing, which is
 * what lets the screen be tested in-process.
 */
final class Proof extends NativeComponent {
    use LoadsFromApi;

    public string $resi = '';

    /** @var array<string, mixed>|null */
    public ?array $form = null;

    public string $recipientName = '';

    public string $relation = '';

    public ?string $photoPath = null;

    public string $notes = '';

    /** idle | done */
    public string $state = 'idle';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?float $accuracy = null;

    public ?int $deviceTimestamp = null;

    /**
     * The fix window shipped with the hand-over, which is the evidence the
     * server checks before accepting proof of delivery.
     *
     * @var list<array{latitude: float, longitude: float, accuracy: float|null, timestamp: int|null}>
     */
    public array $fixWindow = [];

    public string $gpsNotice = '';

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();
        $this->watchLocation();
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->form = null;

            return;
        }

        $result = MobileApi::courier()->get('/courier/deliveries/'.rawurlencode($this->resi).'/proof');

        if (! $result->ok) {
            $this->form = null;
            if ($result->status !== 404 && $result->status !== 422) {
                $this->error = $result->message;
            }

            return;
        }

        $payload = $result->get('proofForm');
        $this->form = CourierPayload::proofForm(is_array($payload) ? $payload : []);
        $this->recipientName = (string) ($this->form['recipient'] ?? '');
        $this->relation = (string) ($this->form['relations'][0] ?? '');
    }

    public function chooseRelation(string $relation): void {
        $this->relation = $relation;
    }

    public function capturePhoto(): void {
        Camera::getPhoto();
    }

    private function watchLocation(): void {
        if (! function_exists('nativephp_call')) {
            return;
        }

        try {
            Geolocation::watchPosition(fineAccuracy: true)->minDistance(5)->start();
        } catch (Throwable) {
            // Best effort: the hand-over still works, the server just gets no fix.
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
    }

    #[On(PhotoTaken::class)]
    public function onPhotoTaken(string $path): void {
        $this->photoPath = $path;
    }

    public function submit(): void {
        if (trim($this->recipientName) === '' || $this->state === 'done') {
            return;
        }

        $payload = [
            'received_by' => trim($this->recipientName),
            'recipient_relation' => $this->relation,
            'receipt_note' => $this->notes,
        ];

        // The coordinates and the window behind them: the server refuses the
        // proof when they show a simulated location or a place far from the
        // destination.
        if ($this->latitude !== null && $this->longitude !== null) {
            $payload['latitude'] = $this->latitude;
            $payload['longitude'] = $this->longitude;
            $payload['device_timestamp'] = $this->deviceTimestamp;
            $payload['gps_signals'] = $this->fixWindow;
        }

        $result = MobileApi::courier()->postFile(
            '/courier/deliveries/'.rawurlencode($this->resi).'/proof',
            $payload,
            $this->photoPath,
        );

        if (! $result->ok) {
            $this->actionError = $this->failureMessage($result);

            return;
        }

        $this->state = 'done';
    }

    /** Ask the hub to review a refused hand-over (usually an out-of-radius fix). */
    public function requestReview(): void {
        $this->actionError = null;

        $result = MobileApi::courier()->post(
            '/courier/deliveries/'.rawurlencode($this->resi).'/gps-review',
            ['action' => 'POD'],
        );

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->gpsNotice = 'Permintaan tinjauan terkirim ke hub.';
    }

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

    public function backToTasks(): void {
        $this->navigate('/courier/tasks');
    }

    public function render(): Element {
        return $this->view('courier.proof', [
            'form' => $this->form,
            'recipientName' => $this->recipientName,
            'relation' => $this->relation,
            'photoPath' => $this->photoPath,
            'state' => $this->state,
            'error' => $this->error,
            'actionError' => $this->actionError,
            'gpsNotice' => $this->gpsNotice,
            'gpsAccuracy' => $this->accuracy !== null ? 'Akurasi sinyal ±'.round($this->accuracy).' m' : null,
            'fixCount' => count($this->fixWindow),
        ]);
    }
}
