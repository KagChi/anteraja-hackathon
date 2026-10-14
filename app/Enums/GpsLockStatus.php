<?php

declare(strict_types=1);

namespace App\Enums;

enum GpsLockStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Tinjauan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::PENDING => Tone::WARNING,
            self::APPROVED => Tone::SUCCESS,
            self::REJECTED => Tone::DANGER,
        };
    }
}
