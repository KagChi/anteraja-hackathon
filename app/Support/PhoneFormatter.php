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

    /**
     * Nomor nasional yang biasa diketik orang Indonesia: +62812… -> 0812….
     */
    public static function toLocalId(string $phone): string
    {
        $e164 = self::toE164Id($phone);

        return str_starts_with($e164, '+62') ? '0'.substr($e164, 3) : $e164;
    }

    /**
     * Bentuk yang mungkin tersimpan di kolom phone: E.164 (+62812…), tanpa
     * tanda plus (62812…), dan nasional (0812…). Staff menyimpan nomornya
     * sebagai nomor nasional dan pembeli sebagai E.164, jadi pencarian
     * pengirim WhatsApp harus mengenali keduanya.
     *
     * @return list<string>
     */
    public static function candidates(string $phone): array
    {
        $e164 = self::toE164Id($phone);

        return array_values(array_unique([
            $e164,
            ltrim($e164, '+'),
            self::toLocalId($e164),
        ]));
    }
}
