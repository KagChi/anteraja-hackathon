<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim satu baris notifikasi ke workflow n8n "anteraja-frozen-outbound".
 *
 * Aplikasi hanya menyerahkan template + payload; format pesan dan pengiriman
 * WhatsApp dikerjakan n8n. Status notifikasi mengikuti jawaban n8n:
 * delivered=true -> SENT, unmapped -> tetap QUEUED, selain itu FAILED.
 */
final class DispatchNotificationToN8n implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public function __construct(private readonly int $notificationId) {}

    public function handle(): void
    {
        /** @var array{enabled?: bool, webhook_url?: string|null, token?: string|null, timeout?: int} $config */
        $config = config('coldchain.n8n', []);

        if (! ($config['enabled'] ?? false) || empty($config['webhook_url'])) {
            return;
        }

        $notification = Notification::find($this->notificationId);

        if ($notification === null || $notification->status !== NotificationStatus::QUEUED) {
            return;
        }

        try {
            $response = Http::timeout((int) ($config['timeout'] ?? 10))
                ->acceptJson()
                ->withHeaders(['X-Anteraja-Token' => (string) ($config['token'] ?? '')])
                ->post((string) $config['webhook_url'], [
                    'notification_id' => $notification->id,
                    'template' => $notification->template,
                    'recipient_type' => $notification->recipient_type->value,
                    'payload' => $notification->payload,
                ]);
        } catch (ConnectionException $exception) {
            // Jaringan atau pod n8n belum siap: biarkan antrean mengulang.
            throw $exception;
        }

        if ($response->failed()) {
            $this->markFailed($notification, "HTTP {$response->status()}");

            return;
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if (($body['delivered'] ?? false) === true) {
            $payload = $notification->payload ?? [];

            if (! empty($body['message_id'])) {
                $payload['wa_message_id'] = (string) $body['message_id'];
            }

            $notification->update([
                'payload' => $payload,
                'status' => NotificationStatus::SENT,
                'sent_at' => now(),
            ]);

            return;
        }

        $reason = (string) ($body['reason'] ?? 'unknown');

        // Template yang memang belum dipetakan ke pesan WhatsApp dibiarkan
        // QUEUED supaya pemetaannya bisa ditambah tanpa kegagalan palsu.
        if (in_array($reason, ['unmapped', 'skipped'], true)) {
            Log::info('Notifikasi dibiarkan QUEUED oleh n8n.', [
                'notification_id' => $notification->id,
                'template' => $notification->template,
                'reason' => $reason,
            ]);

            return;
        }

        $this->markFailed($notification, $reason);
    }

    public function failed(Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null) {
            return;
        }

        $this->markFailed($notification, $exception->getMessage());
    }

    private function markFailed(Notification $notification, string $reason): void
    {
        $payload = $notification->payload ?? [];
        $payload['delivery_error'] = mb_substr($reason, 0, 300);

        $notification->update([
            'payload' => $payload,
            'status' => NotificationStatus::FAILED,
        ]);
    }
}
