<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Facades\Log;

enum IssueCategory: string
{
    case KEMASAN_BOCOR = 'kemasan_bocor';
    case KEMASAN_BASAH = 'kemasan_basah';
    case SUHU_NAIK = 'suhu_naik';
    case PEMBELI_TIDAK_ADA = 'pembeli_tidak_ada';
    case ALAMAT_TIDAK_DITEMUKAN = 'alamat_tidak_ditemukan';
    case COD_DITOLAK = 'cod_ditolak';

    public function label(): string
    {
        return match ($this) {
            self::KEMASAN_BOCOR => 'Kemasan bocor',
            self::KEMASAN_BASAH => 'Kemasan basah luar',
            self::SUHU_NAIK => 'Suhu naik',
            self::PEMBELI_TIDAK_ADA => 'Pembeli tidak di tempat',
            self::ALAMAT_TIDAK_DITEMUKAN => 'Alamat tidak ditemukan',
            self::COD_DITOLAK => 'COD ditolak',
        };
    }

    /**
     * @return array<IssueDecision>
     */
    public function allowedDecisions(): array
    {
        return match ($this) {
            self::KEMASAN_BOCOR, self::KEMASAN_BASAH, self::SUHU_NAIK => [
                IssueDecision::RETURN,
                IssueDecision::RESEND,
                IssueDecision::PROCEED,
            ],
            self::PEMBELI_TIDAK_ADA, self::ALAMAT_TIDAK_DITEMUKAN, self::COD_DITOLAK => [
                IssueDecision::RESCHEDULE,
                IssueDecision::RETURN,
            ],
        };
    }

    public static function fromOrFallback(?string $value): self
    {
        if ($value === null) {
            return self::KEMASAN_BOCOR;
        }

        $case = self::tryFrom($value);
        if ($case !== null) {
            return $case;
        }

        Log::warning("Unknown issue category encountered: {$value}, falling back to kemasan_bocor.");

        return self::KEMASAN_BOCOR;
    }
}
