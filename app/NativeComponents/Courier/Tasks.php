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
use Native\Mobile\Facades\Geolocation;
use Throwable;

/**
 * The courier's task list — the app's home screen — read from
 * GET /api/courier/tasks.
 *
 * The first unaccepted task is on the board; accepting or rejecting it posts
 * to the API, and the distance row is filled from the geolocation fix when the
 * device offers one.
 */
final class Tasks extends NativeComponent {
    use LoadsFromApi;

    /** pending | active | accepted | rejected */
    public string $state = 'pending';

    public bool $toastDismissed = false;

    /** @var array<string, mixed>|null */
    public ?array $task = null;

    public ?string $tracking = null;

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::courier()->get('/courier/tasks');

        if (! $result->ok) {
            $this->error = $result->message;
            $this->task = null;

            return;
        }

        $new = is_array($result->get('newTasks')) ? array_values(array_filter($result->get('newTasks'), 'is_array')) : [];
        $active = is_array($result->get('activeTasks')) ? array_values(array_filter($result->get('activeTasks'), 'is_array')) : [];
        $first = $new[0] ?? $active[0] ?? null;

        if (! is_array($first)) {
            $this->task = null;
            $this->tracking = null;

            return;
        }

        $this->task = CourierPayload::task($first);
        $this->tracking = (string) $this->task['tracking'];
        $this->state = ! empty($first['isNew']) ? 'pending' : 'active';
        $this->requestDistance();
    }

    public function accept(): void {
        if ($this->state !== 'pending' || $this->tracking === null) {
            return;
        }

        $result = MobileApi::courier()->post('/courier/tasks/'.rawurlencode($this->tracking).'/accept');

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->state = 'accepted';
        $this->toastDismissed = false;
    }

    public function reject(): void {
        if ($this->state !== 'pending' || $this->tracking === null) {
            return;
        }

        $result = MobileApi::courier()->post('/courier/tasks/'.rawurlencode($this->tracking).'/reject', [
            'reason' => 'Ditolak dari aplikasi kurir',
        ]);

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->state = 'rejected';
        $this->toastDismissed = false;
    }

    public function dismissToast(): void {
        $this->toastDismissed = true;
    }

    /** The line shown in the toast once a decision has been made. */
    public function toast(): ?string {
        return match ($this->state) {
            'accepted' => 'Tugas berhasil diterima! Navigasi segera dibuka.',
            'rejected' => 'Tugas '.($this->tracking ?? '').' telah ditolak.',
            default => null,
        };
    }

    /** Ask the device for one fix; the distance endpoint answers on the event. */
    private function requestDistance(): void {
        if (! function_exists('nativephp_call')) {
            return;
        }

        try {
            Geolocation::getCurrentPosition()->get();
        } catch (Throwable) {
            // Location is best-effort; the row keeps its placeholder.
        }
    }

    #[On(LocationReceived::class)]
    public function onLocationReceived(bool $success, ?float $latitude = null, ?float $longitude = null): void {
        if (! $success || $latitude === null || $longitude === null || $this->tracking === null) {
            return;
        }

        $result = MobileApi::courier()->get('/courier/tasks/'.rawurlencode($this->tracking).'/distance', [
            'lat' => $latitude,
            'lng' => $longitude,
        ]);

        if (! $result->ok || $this->task === null) {
            return;
        }

        $km = $result->get('distance_km');
        $eta = $result->get('eta_minutes');

        if (is_numeric($km)) {
            $this->task['distance'] = rtrim(rtrim(number_format((float) $km, 1, '.', ''), '0'), '.').' km dari lokasi Anda';
        }

        if (is_numeric($eta)) {
            $this->task['eta'] = '~'.(int) $eta.' Menit';
        }
    }

    public function render(): Element {
        return $this->view('courier.tasks', [
            'task' => $this->task,
            'toast' => $this->toastDismissed ? null : $this->toast(),
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
