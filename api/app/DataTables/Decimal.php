<?php

namespace App\DataTables;

/**
 * Money arithmetic without float surprises (0.1 × 3 is 0.3, not
 * 0.30000000000000004): exact decimal math with bcmath, which the
 * production image has, rounded half up to cents.
 */
final class Decimal
{
    public static function multiply(int|float $a, int|float $b): int|float
    {
        if (! function_exists('bcmul')) {
            return self::round($a * $b);
        }

        return self::round((float) bcmul(self::string($a), self::string($b), 8));
    }

    /** To cents, half up; whole numbers as int. */
    public static function round(int|float $value): int|float
    {
        $rounded = round($value, 2, PHP_ROUND_HALF_UP);

        return floor($rounded) == $rounded && abs($rounded) < PHP_INT_MAX ? (int) $rounded : $rounded;
    }

    /** 1.0E-5 → "0.00001": bcmath needs plain decimal strings. */
    private static function string(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
