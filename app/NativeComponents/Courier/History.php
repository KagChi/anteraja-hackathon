<?php

declare(strict_types=1);

namespace App\NativeComponents\Courier;

use App\Support\Concerns\LoadsFromApi;
use App\Support\CourierPayload;
use App\Support\MobileApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

/**
 * Completed deliveries for the day — GET /api/courier/history.
 */
final class History extends NativeComponent {
    use LoadsFromApi;

    /** @var list<array<string, mixed>> */
    public array $completed = [];

    public string $onTime = '97%';

    public function mount(): void {
        $this->load();
    }

    protected function load(): void {
        $result = MobileApi::courier()->get('/courier/history');

        if (! $result->ok) {
            $this->error = $result->message;
            $this->completed = [];

            return;
        }

        $rows = is_array($result->get('history')) ? $result->get('history') : [];
        $this->completed = array_values(array_map(
            static fn (array $row): array => CourierPayload::historyRow($row),
            array_filter($rows, 'is_array'),
        ));

        $summary = $result->get('summary');
        if (is_array($summary) && is_string($summary['onTimeRate'] ?? null)) {
            $this->onTime = trim(str_replace('Tepat Waktu', '', $summary['onTimeRate']));
        }
    }

    public function render(): Element {
        return $this->view('courier.history', [
            'completed' => $this->completed,
            'onTime' => $this->onTime,
            'error' => $this->error,
        ]);
    }
}
