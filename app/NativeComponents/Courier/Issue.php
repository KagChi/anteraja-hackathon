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
 * Field issue report — the escape hatch when a delivery cannot be completed.
 *
 * Reason, note and photo are all required because the hub acts on this
 * without a phone call; the photo is what lets them decide between a resend
 * and a cold-chain breach.
 */
final class Issue extends NativeComponent {
    public string $resi = '';

    public string $issueType = '';

    public string $note = '';

    public ?string $photoPath = null;

    /** idle | done */
    public string $state = 'idle';

    public function mount(): void {
        $form = CourierData::issueForm();

        $this->resi = (string) ($this->param('resi') ?: $form['tracking']);
        $this->issueType = $form['options'][0]['label'];
        $this->note = $form['note'];
    }

    public function capturePhoto(): void {
        Camera::getPhoto();
    }

    #[On(PhotoTaken::class)]
    public function onPhotoTaken(string $path): void {
        $this->photoPath = $path;
    }

    public function clearPhoto(): void {
        $this->photoPath = null;
    }

    public function submit(): void {
        if ($this->state === 'done') {
            return;
        }

        $this->state = 'done';
    }

    public function backToTasks(): void {
        $this->navigate('/courier/tasks');
    }

    /**
     * The native select takes a flat list of strings, so it binds to the
     * option label. The fixture's machine keys are only meaningful once the
     * report is posted to the hub, which the prototype does not do yet.
     */
    public function typeOptions(): array {
        return array_column(CourierData::issueForm()['options'], 'label');
    }

    public function render(): Element {
        return $this->view('courier.issue', [
            'form' => CourierData::issueForm(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
