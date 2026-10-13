<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\CourierData;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * The courier's task list — the app's home screen.
 *
 * One task is on the board in the prototype, so the screen is the task card
 * plus its accept / reject decision. State is the decision itself, which is
 * all the native screen has to remember between renders.
 */
final class Tasks extends NativeComponent {
    /** pending | accepted | rejected */
    public string $state = 'pending';

    public bool $toastDismissed = false;

    public function accept(): void {
        if ($this->state !== 'pending') {
            return;
        }

        $this->state = 'accepted';
        $this->toastDismissed = false;
    }

    public function reject(): void {
        if ($this->state !== 'pending') {
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
            'rejected' => 'Tugas '.CourierData::task()['tracking'].' telah ditolak.',
            default => null,
        };
    }

    public function render(): Element {
        return $this->view('courier.tasks', [
            'courier' => CourierData::courier(),
            'task' => CourierData::task(),
            'toast' => $this->toastDismissed ? null : $this->toast(),
        ]);
    }
}
