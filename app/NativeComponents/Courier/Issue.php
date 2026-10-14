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
 * Field issue report — the escape hatch when a delivery cannot be completed —
 * POST /api/courier/deliveries/{resi}/issue.
 *
 * Reason, note and photo are all required because the hub acts on this
 * without a phone call; the photo is what lets them decide between a resend
 * and a cold-chain breach.
 */
final class Issue extends NativeComponent {
    use LoadsFromApi;

    public string $resi = '';

    /** @var array<string, mixed>|null */
    public ?array $form = null;

    public string $issueType = '';

    public string $note = '';

    public ?string $photoPath = null;

    /** idle | done */
    public string $state = 'idle';

    public function mount(): void {
        $this->resi = (string) ($this->param('resi') ?? '');
        $this->load();

        $reason = (string) ($this->param('reason') ?? '');
        if ($reason !== '' && in_array($reason, $this->typeOptions(), true)) {
            $this->issueType = $reason;
        }
    }

    protected function load(): void {
        if ($this->resi === '') {
            $this->form = null;

            return;
        }

        $result = MobileApi::courier()->get('/courier/deliveries/'.rawurlencode($this->resi).'/issue');

        if (! $result->ok) {
            $this->form = null;
            if ($result->status !== 404 && $result->status !== 422) {
                $this->error = $result->message;
            }

            return;
        }

        $payload = $result->get('issueForm');
        $this->form = CourierPayload::issueForm(is_array($payload) ? $payload : []);
        $this->issueType = (string) ($this->form['options'][0]['label'] ?? '');
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
        if ($this->state === 'done' || $this->form === null) {
            return;
        }

        $result = MobileApi::courier()->postFile('/courier/deliveries/'.rawurlencode($this->resi).'/issue', [
            'issue_category' => $this->selectedCategory(),
            'description' => trim($this->note) !== '' ? trim($this->note) : 'Kendala dilaporkan dari aplikasi kurir.',
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

    /**
     * The native select binds to the option label, so the machine key is looked
     * back up here before the report is posted.
     */
    private function selectedCategory(): string {
        foreach (is_array($this->form['options'] ?? null) ? $this->form['options'] : [] as $option) {
            if (($option['label'] ?? '') === $this->issueType) {
                return (string) ($option['value'] ?? 'kemasan_bocor');
            }
        }

        return 'kemasan_bocor';
    }

    /**
     * The native select takes a flat list of strings, so it binds to the
     * option label.
     *
     * @return list<string>
     */
    public function typeOptions(): array {
        $labels = [];

        foreach (is_array($this->form['options'] ?? null) ? $this->form['options'] : [] as $option) {
            $label = (string) ($option['label'] ?? '');
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function render(): Element {
        return $this->view('courier.issue', [
            'form' => $this->form,
            'typeOptions' => $this->typeOptions(),
            'issueType' => $this->issueType,
            'note' => $this->note,
            'photoPath' => $this->photoPath,
            'state' => $this->state,
            'error' => $this->error,
            'actionError' => $this->actionError,
        ]);
    }
}
