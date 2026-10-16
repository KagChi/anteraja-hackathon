<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageStatus: string {
    case CREATED = 'CREATED';
    case AWAITING_CONFIRMATION = 'AWAITING_CONFIRMATION';
    case CONFIRMED = 'CONFIRMED';
    case COURIER_ASSIGNED = 'COURIER_ASSIGNED';
    case CHECKED = 'CHECKED';
    case ISSUE_REPORTED = 'ISSUE_REPORTED';
    case IN_DELIVERY = 'IN_DELIVERY';
    case RECEIVED = 'RECEIVED';
    case RETURNED = 'RETURNED';
    case CLOSED = 'CLOSED';

    public function tone(): Tone {
        return match ($this) {
            self::CREATED => Tone::NEUTRAL,
            self::AWAITING_CONFIRMATION => Tone::MUTED,
            self::CONFIRMED, self::COURIER_ASSIGNED, self::CHECKED => Tone::INFO,
            self::ISSUE_REPORTED => Tone::DANGER,
            self::IN_DELIVERY => Tone::DARK,
            self::RECEIVED => Tone::SUCCESS,
            self::RETURNED => Tone::MUTED,
            self::CLOSED => Tone::NEUTRAL,
        };
    }

    public function isWaiting(): bool {
        return match ($this) {
            self::AWAITING_CONFIRMATION, self::ISSUE_REPORTED => true,
            default => false,
        };
    }

    public function isTerminal(): bool {
        return $this === self::CLOSED;
    }
}
