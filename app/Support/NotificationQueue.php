<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IssueCategory;
use App\Enums\NotificationStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Jobs\DispatchNotificationToN8n;
use App\Models\Notification;
use App\Models\Package;
use App\Models\Staff;
use App\Services\Client\ClientLinkService;
use Carbon\CarbonInterface;

final class NotificationQueue
{
    public function __construct(
        private readonly ?ClientLinkService $linkService = null
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(
        Package $package,
        RecipientType $type,
        int $recipientId,
        string $template,
        array $payload = []
    ): Notification {
        if ($type === RecipientType::BUYER && ! isset($payload['client_url'])) {
            $linkService = $this->linkService ?? app(ClientLinkService::class);
            $payload['client_url'] = $linkService->entry($package);
        }

        $payload = $this->withDeliveryContext($package, $type, $recipientId, $payload);

        $notification = Notification::create([
            'package_id' => $package->id,
            'recipient_type' => $type,
            'recipient_id' => $recipientId,
            'template' => $template,
            'payload' => $payload,
            'status' => NotificationStatus::QUEUED,
        ]);

        DispatchNotificationToN8n::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    public function queueTaskRejected(Package $package, Staff $courier, ?string $reason): void
    {
        $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();
        foreach ($admins as $admin) {
            $this->queue(
                $package,
                RecipientType::ADMIN,
                $admin->id,
                'task_rejected',
                [
                    'tracking_number' => $package->tracking_number,
                    'courier_name' => $courier->name,
                    'reason' => $reason ?? 'Kurir menolak penugasan',
                ]
            );
        }
    }

    /**
     * Pesan ke staf yang tidak terikat pada satu paket, misalnya ringkasan
     * harian untuk admin. package_id sengaja kosong: laporan itu tentang
     * seluruh armada, bukan satu resi.
     *
     * @param  array<string, mixed>  $payload
     */
    public function queueStaffReport(Staff $staff, string $template, array $payload): Notification
    {
        $payload['recipient_type'] = RecipientType::ADMIN->value;
        $payload['recipient_name'] ??= $staff->name;
        $payload['recipient_phone'] ??= self::normalizePhone($staff->phone);

        $notification = Notification::create([
            'package_id' => null,
            'recipient_type' => RecipientType::ADMIN,
            'recipient_id' => $staff->id,
            'template' => $template,
            'payload' => $payload,
            'status' => NotificationStatus::QUEUED,
        ]);

        DispatchNotificationToN8n::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    public function queueOnTheWay(Package $package, Staff $courier): void
    {
        $this->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'on_the_way',
            [
                'tracking_number' => $package->tracking_number,
                'courier_name' => $courier->name,
                'window' => DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at),
            ]
        );
    }

    public function queueCourierArrived(Package $package, Staff $courier): void
    {
        $this->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'courier_arrived',
            [
                'tracking_number' => $package->tracking_number,
                'courier_name' => $courier->name,
            ]
        );
    }

    public function queueDelivered(Package $package, string $receivedBy, CarbonInterface $deliveredAt): void
    {
        $this->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'delivered',
            [
                'tracking_number' => $package->tracking_number,
                'received_by' => $receivedBy,
                'delivered_at' => $deliveredAt->toIso8601String(),
            ]
        );
    }

    public function queueIssueReported(Package $package, Staff $courier, string $issueCategory): void
    {
        $label = IssueCategory::fromOrFallback($issueCategory)->label();

        $admins = Staff::where('role', StaffRole::ADMIN)->where('is_active', true)->get();
        foreach ($admins as $admin) {
            $this->queue(
                $package,
                RecipientType::ADMIN,
                $admin->id,
                'issue_reported',
                [
                    'tracking_number' => $package->tracking_number,
                    'issue_category' => $issueCategory,
                    // The admin's WhatsApp message names the problem; the raw
                    // category code would only be readable to the console.
                    'issue_label' => $label,
                    'courier_name' => $courier->name,
                ]
            );
        }
    }

    /**
     * Menu pilihan penyelesaian kendala (opsi 1-4) untuk pembeli.
     */
    public function queueIssueOptions(Package $package, IssueCategory $category): void
    {
        $this->queue(
            $package,
            RecipientType::BUYER,
            $package->buyer_id,
            'issue_options',
            [
                'tracking_number' => $package->tracking_number,
                'issue_category' => $category->value,
                'issue_label' => $category->label(),
            ]
        );
    }

    /**
     * Payload notifikasi dibuat mandiri supaya workflow n8n tidak perlu
     * membuka database aplikasi: nomor penerima, nama, alamat, resi, dan token
     * tautan pembeli ikut dikirim bersama template.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDeliveryContext(
        Package $package,
        RecipientType $type,
        int $recipientId,
        array $payload
    ): array {
        $package->loadMissing(['buyer', 'courier']);

        if ($type === RecipientType::BUYER) {
            $phone = $package->buyer?->phone;
            $name = $package->buyer?->name;
            $payload['address'] ??= $package->buyer?->address;
            $payload['buyer_name'] ??= $package->buyer?->name;
        } else {
            $staff = Staff::find($recipientId);
            $phone = $staff?->phone;
            $name = $staff?->name;
        }

        $payload['recipient_type'] = $type->value;
        $payload['recipient_name'] ??= $name;
        $payload['recipient_phone'] ??= self::normalizePhone($phone);
        $payload['tracking_number'] ??= $package->tracking_number;
        $payload['client_token'] ??= $package->client_token;
        $payload['courier_name'] ??= $package->courier?->name;
        // Every schedule template renders this line as the delivery window.
        // Without it they fall back to the raw scheduled_at timestamp, which is
        // what the buyer would otherwise read.
        $payload['window'] ??= DisplayTime::windowLabel($package->scheduled_at, $package->scheduled_end_at);

        return $payload;
    }

    private static function normalizePhone(?string $phone): ?string
    {
        $phone = $phone === null ? null : trim($phone);

        if ($phone === null || $phone === '') {
            return null;
        }

        return PhoneFormatter::toE164Id($phone);
    }
}
