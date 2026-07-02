<?php

namespace App\Support;

class PhoneNormalizer
{
    /** Normalize to Pakistan mobile format e.g. 03001234567 */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '92') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && ! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /** @return list<string> */
    public static function variants(?string $phone): array
    {
        $normalized = static::normalize($phone);
        if ($normalized === null) {
            return [];
        }

        $variants = [$normalized, $phone];

        if (str_starts_with($normalized, '0')) {
            $variants[] = '92'.substr($normalized, 1);
            $variants[] = '+92'.substr($normalized, 1);
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
