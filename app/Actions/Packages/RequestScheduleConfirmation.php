<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Enums\ConfirmationResponse;
use App\Enums\PackageStatus;
use App\Enums\RecipientType;
use App\Enums\StaffRole;
use App\Exceptions\ScheduleNotActionable;
use App\Models\Package;
use App\Models\ScheduleConfirmation;
use App\Models\Staff;
use App\Support\NotificationQueue;
use App\Support\PackageStatusTransitioner;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ask the recipient to confirm the receiving date, optionally proposing a new
 * window at the same time.
 *
 * The hub assigns the courier first; only then, from the app or the console,
 * may a courier or an admin send the schedule request. This is what turns a
 * COURIER_ASSIGNED parcel into AWAITING_CONFIRMATION and queues the WhatsApp
 * message the recipient answers.
 *
 * When a new window is supplied the caller is changing the schedule rather than
 * just asking for the standing one: the parcel's scheduled_at/scheduled_end_at
 * are written first, and the buyer still has to confirm the proposal before it
 * counts as agreed. Both the courier and the admin use this same action so the
 * behaviour stays identical; the only role difference is that a courier may
 * only act on a parcel assigned to them.
 */
final class RequestScheduleConfirmation {
    public function __construct(
        private readonly PackageStatusTransitioner $transitioner,
        private readonly NotificationQueue $notifications,
    ) {
    }

    public function execute(
        Package $package,
        Staff $actor,
        ?CarbonImmutable $newScheduledAt = null,
        ?CarbonImmutable $newScheduledEndAt = null,
    ): Package {
        return DB::transaction(function () use ($package, $actor, $newScheduledAt, $newScheduledEndAt): Package {
            /** @var Package $locked */
            $locked = Package::where('id', $package->id)->lockForUpdate()->firstOrFail();

            if ($actor->role === StaffRole::COURIER && $locked->courier_id !== $actor->id) {
                throw new ScheduleNotActionable('Paket tidak ditugaskan ke kurir ini.');
            }

            if ($locked->status !== PackageStatus::COURIER_ASSIGNED) {
                throw new ScheduleNotActionable('Konfirmasi jadwal dapat diminta setelah kurir ditugaskan.');
            }

            $hasPending = $locked->scheduleConfirmations()
                ->where('response', 'PENDING')
                ->exists();

            if ($hasPending) {
                return $locked;
            }

            $proposingWindow = $newScheduledAt !== null && $newScheduledEndAt !== null;

            ScheduleConfirmation::create([
                'package_id' => $locked->id,
                'response' => ConfirmationResponse::PENDING,
                'proposed_scheduled_at' => $proposingWindow ? $newScheduledAt : $locked->scheduled_at,
                'requested_at' => Carbon::now('UTC'),
            ]);

            $attributes = $proposingWindow
                ? [
                    'scheduled_at' => $newScheduledAt,
                    'scheduled_end_at' => $newScheduledEndAt,
                ]
                : [];

            $locked = $this->transitioner->transition(
                package: $locked,
                to: PackageStatus::AWAITING_CONFIRMATION,
                actor: $actor,
                attributes: $attributes,
            );

            // A changed window is worded as a proposal from the hub; an
            // unchanged one is simply the standing schedule request.
            $this->notifications->queue(
                package: $locked,
                type: RecipientType::BUYER,
                recipientId: $locked->buyer_id,
                template: $proposingWindow ? 'schedule_rescheduled' : 'schedule_request',
                payload: [
                    'tracking_number' => $locked->tracking_number,
                    'scheduled_at' => $locked->scheduled_at?->toIso8601String(),
                    'scheduled_end_at' => $locked->scheduled_end_at?->toIso8601String(),
                ],
            );

            return $locked;
        });
    }
}
