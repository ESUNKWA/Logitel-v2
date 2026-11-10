<?php

namespace App\Support;

/**
 * Phone numbers grouped for reading (receipts, client request 2026-11-10): Ivorian numbers by
 * pairs — « 05 46 53 34 14 », « +225 05 46 53 34 14 ». Anything else (foreign numbers,
 * unusual lengths) is returned as typed, only trimmed.
 */
class PhoneFormat
{
    public static function display(?string $phone): string
    {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $international = str_starts_with($phone, '+') || str_starts_with($digits, '00225');
        $digits = str_starts_with($digits, '00225') ? substr($digits, 2) : $digits;

        if (strlen($digits) === 10 && ! $international) {
            return self::pairs($digits);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '225')) {
            return '+225 '.self::pairs(substr($digits, 3));
        }

        return $phone;
    }

    /**
     * What a table search should match for this number (data-search): as typed, grouped by pairs
     * and digits only — « 0546533414 », « 05 46 53 » or « +225 05… » all find it.
     */
    public static function searchText(?string $phone): string
    {
        $phone = trim((string) $phone);

        return $phone === '' ? '' : implode(' ', array_unique([$phone, self::display($phone), preg_replace('/\D/', '', $phone) ?? '']));
    }

    private static function pairs(string $digits): string
    {
        return implode(' ', str_split($digits, 2));
    }
}
