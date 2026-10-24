<?php

namespace App\Security;

/**
 * Phone numbers in E.164 (+37455123456). A local Armenian number may be
 * typed as 055 12 34 56 or 55123456; a foreign one starts with + or 00.
 */
final class PhoneNumber
{
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s().-]+/', '', trim($input)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '+')) {
            return preg_match('/^\+[1-9]\d{7,14}$/', $digits) ? $digits : null;
        }

        // Armenia: 0XX XXXXXX or XX XXXXXX (8 digits after the leading 0).
        if (preg_match('/^0?(\d{8})$/', $digits, $m)) {
            return '+374'.$m[1];
        }

        if (preg_match('/^374(\d{8})$/', $digits, $m)) {
            return '+374'.$m[1];
        }

        return null;
    }

    /** +374 55 •• •• 56: enough to recognise it, not to read it out. */
    public static function mask(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 6) {
            return $phone;
        }

        return substr($phone, 0, 6).str_repeat('•', max(0, strlen($phone) - 8)).substr($phone, -2);
    }
}
