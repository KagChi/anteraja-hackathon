<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much the server trusts a fix window.
 *
 * CLEAN     -- nothing to see, the action proceeds.
 * SUSPECTED -- weak signals only; the action proceeds but is flagged.
 * BLOCKED   -- strong signals; the action is refused until the hub reviews it.
 */
enum GpsLevel: string
{
    case CLEAN = 'CLEAN';
    case SUSPECTED = 'SUSPECTED';
    case BLOCKED = 'BLOCKED';

    public function label(): string
    {
        return match ($this) {
            self::CLEAN => 'Normal',
            self::SUSPECTED => 'Perlu Ditinjau',
            self::BLOCKED => 'Diblokir',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::CLEAN => Tone::SUCCESS,
            self::SUSPECTED => Tone::WARNING,
            self::BLOCKED => Tone::DANGER,
        };
    }
}
