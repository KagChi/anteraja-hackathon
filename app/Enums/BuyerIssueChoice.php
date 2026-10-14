<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pilihan penyelesaian kendala yang dikirim pembeli lewat WhatsApp.
 *
 * Nomor opsi mengikuti pesan "Konfirmasi Penyelesaian Kendala ke Pembeli"
 * pada workflow n8n; nilainya sengaja memakai kembali nilai IssueDecision
 * supaya konsisten saat dibaca admin.
 */
enum BuyerIssueChoice: string
{
    case RETURN = 'RETURN';
    case RESEND = 'RESEND';
    case PROCEED = 'PROCEED';
    case DISCUSS = 'DISCUSS';

    public static function fromOption(int $option): ?self
    {
        return match ($option) {
            1 => self::RETURN,
            2 => self::RESEND,
            3 => self::PROCEED,
            4 => self::DISCUSS,
            default => null,
        };
    }

    public function option(): int
    {
        return match ($this) {
            self::RETURN => 1,
            self::RESEND => 2,
            self::PROCEED => 3,
            self::DISCUSS => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RETURN => 'Pengembalian Paket ke Pengirim',
            self::RESEND => 'Pengiriman Ulang',
            self::PROCEED => 'Lanjutkan Pengiriman dengan Kompensasi Parsial',
            self::DISCUSS => 'Diskusi Lebih Lanjut',
        };
    }
}
