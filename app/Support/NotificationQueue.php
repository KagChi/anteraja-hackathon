<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\NotificationStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
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

        return Notification::create([
            'package_id' => $package->id,
            'recipient_type' => $type,
            'recipient_id' => $recipientId,
            'template' => $template,
            'payload' => $payload,
            'status' => NotificationStatus::QUEUED,
        ]);
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
                    'courier_name' => $courier->name,
                ]
            );
        }
    }
}
