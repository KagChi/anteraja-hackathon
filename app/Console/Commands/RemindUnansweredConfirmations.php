<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Models\Notification;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\NotificationQueue;
use Illuminate\Console\Command;

final class RemindUnansweredConfirmations extends Command
{
    protected $signature = 'confirmations:remind-unanswered';

    protected $description = 'Remind admins of buyer schedule confirmations that have remained unanswered beyond the threshold';

    public function handle(NotificationQueue $notificationQueue): int
    {
        $thresholdMinutes = (int) config('coldchain.client.unanswered_after_minutes', 120);
        $cutoff = now()->subMinutes($thresholdMinutes);

        $overdueConfirmations = ScheduleConfirmation::where('response', 'PENDING')
            ->where('requested_at', '<=', $cutoff)
            ->whereHas('package', function ($query) {
                $query->where('status', PackageStatus::AWAITING_CONFIRMATION);
            })
            ->with('package')
            ->get();

        if ($overdueConfirmations->isEmpty()) {
            $this->info('No overdue confirmations found.');

            return self::SUCCESS;
        }

        $activeAdmins = Staff::where('role', StaffRole::ADMIN)
            ->where('is_active', true)
            ->get();

        $queuedCount = 0;

        foreach ($overdueConfirmations as $confirmation) {
            $package = $confirmation->package;
            if ($package === null) {
                continue;
            }

            // Deduplication: skip if confirmation_overdue notification already exists for this confirmation_id
            $alreadyNotified = Notification::where('recipient_type', RecipientType::ADMIN)
                ->where('template', 'confirmation_overdue')
                ->whereRaw("payload->>'confirmation_id' = ?", [(string) $confirmation->id])
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $ageMinutes = $confirmation->requested_at !== null
                ? (int) $confirmation->requested_at->diffInMinutes(now())
                : $thresholdMinutes;

            foreach ($activeAdmins as $admin) {
                $notificationQueue->queue(
                    $package,
                    RecipientType::ADMIN,
                    $admin->id,
                    'confirmation_overdue',
                    [
                        'tracking_number' => $package->tracking_number,
                        'confirmation_id' => (string) $confirmation->id,
                        'age_minutes' => $ageMinutes,
                    ]
                );
            }

            $queuedCount++;
        }

        $this->info("Processed overdue confirmations. Queued reminders for {$queuedCount} packages.");

        return self::SUCCESS;
    }
}
