<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\CourierPayload;
use App\Support\MobileApi;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Facades\Camera;

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

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();
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

    #[On(PhotoTaken::class)]
    public function onPhotoTaken(string $path): void {
        $this->photoPath = $path;
    }

    public function submit(): void {
        if (trim($this->recipientName) === '' || $this->state === 'done') {
            return;
        }

        $result = MobileApi::courier()->postFile('/courier/deliveries/'.rawurlencode($this->resi).'/proof', [
            'received_by' => trim($this->recipientName),
            'recipient_relation' => $this->relation,
            'receipt_note' => $this->notes,
        ], $this->photoPath);

        if (! $result->ok) {
            $this->actionError = $result->message;

            return;
        }

        $this->state = 'done';
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
        ]);
    }
}
