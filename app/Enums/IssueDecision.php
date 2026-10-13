<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueDecision: string
{
    case RETURN = 'RETURN';
    case RESEND = 'RESEND';
    case PROCEED = 'PROCEED';
    case RESCHEDULE = 'RESCHEDULE';
    case RECHECK = 'RECHECK';

    public function label(): string
    {
        return match ($this) {
            self::RETURN => 'Kembalikan Paket',
            self::RESEND => 'Kirim Ulang',
            self::PROCEED => 'Lanjutkan',
            self::RESCHEDULE => 'Jadwal Ulang',
            self::RECHECK => 'Periksa Ulang',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::RETURN => 'Paket ditarik ke DC untuk klaim retur',
            self::RESEND => 'Buat resi baru slot pengiriman terdekat dari hub asal',
            self::PROCEED => 'Kompensasi parsial jika disetujui pembeli',
            self::RESCHEDULE => 'Minta pembeli memilih jadwal baru',
            self::RECHECK => 'Pemeriksaan ulang fisik di lapangan',
        };
    }
}
