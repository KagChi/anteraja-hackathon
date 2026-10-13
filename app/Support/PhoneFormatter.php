<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneFormatter
{
    public static function toE164Id(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            return '+62'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '62')) {
            return '+'.$cleaned;
        }

        return '+62'.$cleaned;
    }
}
