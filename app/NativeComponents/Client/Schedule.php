<?php

declare(strict_types=1);

namespace App\NativeComponents\Client;

use App\Support\Concerns\LoadsFromApi;
use App\Support\ClientData;
use App\Support\ClientPayload;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * The delivery window the customer is asked to accept, and the two ways out:
 * confirm it, or ask for another one — GET/POST
 * /api/client/orders/{resi}/schedule...
 */
final class Schedule extends NativeComponent {
    use LoadsFromApi;

    /** idle | confirmed | rescheduled */
    public string $state = 'idle';

    /** @var array<string, mixed>|null */
    public ?array $order = null;

    /** @var array<string, mixed>|null */
    public ?array $schedule = null;

    public string $resi = '';

    public bool $rescheduleOpen = false;

    public string $rescheduleDate = '';

    public string $rescheduleSlot = '';

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->rescheduleDate = now('Asia/Jakarta')->format('Y-m-d');
        $this->load();
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->order = null;
            $this->schedule = null;

            return;
        }

        $result = MobileApi::client()->get('/client/orders/'.rawurlencode($this->resi).'/schedule');

        if (! $result->ok) {
            $this->order = null;
            $this->schedule = null;
            if ($result->status !== 404) {
                $this->error = $result->message;
            }

            return;
        }

        $data = $result->data();
        $this->order = ClientPayload::orderCard($data);
        $this->schedule = ClientPayload::schedule($data);
    }

    public function confirm(): void {
        if ($this->state !== 'idle' || $this->schedule === null) {
            return;
        }

        $result = MobileApi::client()->post('/client/orders/'.rawurlencode($this->resi).'/schedule/confirm');

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->state = 'confirmed';
    }

    public function requestOtherWindow(): void {
        if ($this->schedule === null) {
            return;
        }

        $this->rescheduleSlot = '';
        $this->rescheduleOpen = true;
    }

    public function closeReschedule(): void {
        $this->rescheduleOpen = false;
    }

    public function chooseRescheduleDate(string $date): void {
        $this->rescheduleDate = $date;
    }

    public function chooseRescheduleSlot(string $slot): void {
        $this->rescheduleSlot = $slot;
    }

    public function submitReschedule(): void {
        if ($this->schedule === null || $this->rescheduleSlot === '') {
            return;
        }

        $result = MobileApi::client()->post('/client/orders/'.rawurlencode($this->resi).'/schedule/reschedule', [
            'date' => $this->rescheduleDate,
            'slot' => $this->rescheduleSlot,
        ]);

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->rescheduleOpen = false;
        $this->state = 'rescheduled';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function dateOptions(): array {
        $today = now('Asia/Jakarta');

        return [
            ['value' => $today->format('Y-m-d'), 'label' => 'Hari Ini'],
            ['value' => $today->copy()->addDay()->format('Y-m-d'), 'label' => 'Besok'],
        ];
    }

    public function openSupport(): void {
        Browser::open(ClientData::whatsappLink('Halo, saya butuh bantuan untuk pesanan '.$this->resi.'.'));
    }

    public function render(): Element {
        return $this->view('client.schedule', [
            'order' => $this->order,
            'schedule' => $this->schedule,
            'resi' => $this->resi,
            'state' => $this->state,
            'rescheduleOpen' => $this->rescheduleOpen,
            'rescheduleDate' => $this->rescheduleDate,
            'rescheduleSlot' => $this->rescheduleSlot,
            'dateOptions' => $this->dateOptions(),
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
