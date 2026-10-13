<?php

declare(strict_types=1);

namespace App\Enums;

enum ResolutionStatus: string
{
    case MENUNGGU_KEPUTUSAN = 'Menunggu Keputusan';
    case SELESAI = 'Selesai';
    case DILANJUTKAN = 'Dilanjutkan';
    case PROSES_KIRIM_ULANG = 'Proses Kirim Ulang';
    case DIJADWALKAN = 'Dijadwalkan';
    case PEMERIKSAAN_ULANG = 'Pemeriksaan Ulang';

    public function tone(): Tone
    {
        return match ($this) {
            self::MENUNGGU_KEPUTUSAN => Tone::WARNING,
            self::SELESAI => Tone::SUCCESS,
            self::DILANJUTKAN => Tone::INFO,
            self::PROSES_KIRIM_ULANG => Tone::MUTED,
            self::DIJADWALKAN => Tone::MUTED,
            self::PEMERIKSAAN_ULANG => Tone::DANGER,
        };
    }
}
