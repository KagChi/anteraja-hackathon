<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\CourierData;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Facades\Camera;

/**
 * Proof of delivery: who physically took the parcel, the photo that shows it,
 * and an optional note.
 *
 * The photo comes from the device camera rather than a file input — the whole
 * reason this screen is native. Off-device the capture does nothing, which is
 * what lets the screen be tested in-process.
 */
final class Proof extends NativeComponent {
    public string $resi = '';

    public string $recipientName = '';

    public string $relation = '';

    public ?string $photoPath = null;

    public string $notes = '';

    /** idle | done */
    public string $state = 'idle';

    public function mount(): void {
        $form = CourierData::proofForm();

        $this->resi = (string) ($this->param('resi') ?: $form['tracking']);
        $this->recipientName = $form['recipient'];
        $this->relation = $form['relations'][0];
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

        $this->state = 'done';
    }

    public function backToTasks(): void {
        $this->navigate('/courier/tasks');
    }

    public function render(): Element {
        return $this->view('courier.proof', [
            'form' => CourierData::proofForm(),
        ]);
    }
}
