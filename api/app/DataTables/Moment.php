<?php

namespace App\DataTables;

use DateTimeImmutable;

/**
 * Reads the date (and time, when there is one) of a cell typed in the admin
 * or read from a file: 2026-10-17, 17.10.2026 14:35, 10/17/2026 2:35 PM,
 * 2026-10-17T14:35:00+04:00, or a spreadsheet serial number.
 *
 * "10/09/2026" alone can be 10 September or 9 October. A file uses one order
 * for the whole column, so the importer looks at all its values first
 * (detectOrder) and reads every cell with that order. Without a hint the day
 * comes first, as in Armenia.
 */
final class Moment
{
    public const DAY_FIRST = 'dmy';

    public const MONTH_FIRST = 'mdy';

    private const TIME = '(?:[T\s,]+(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?\s*([AaPp][Mm])?)?\s*(?:Z|[+-]\d{2}:?\d{2})?';

    /**
     * @return array{date: string, time: ?string}|null the date as Y-m-d and the time as H:i
     */
    public static function parse(mixed $raw, ?string $order = null): ?array
    {
        if (is_int($raw) || is_float($raw) || (is_string($raw) && preg_match('/^\d{5}(\.\d+)?$/', $raw))) {
            return self::serial((float) $raw);
        }

        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        if (preg_match('/^(\d{4})[-.\/](\d{1,2})[-.\/](\d{1,2})'.self::TIME.'$/', $value, $m)) {
            return self::make((int) $m[1], (int) $m[2], (int) $m[3], $m);
        }

        if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4}|\d{2})'.self::TIME.'$/', $value, $m)) {
            [$a, $b] = [(int) $m[1], (int) $m[2]];
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            $monthFirst = match (true) {
                $a > 12 => false,
                $b > 12 => true,
                default => $order === self::MONTH_FIRST,
            };

            return $monthFirst ? self::make($year, $a, $b, $m) : self::make($year, $b, $a, $m);
        }

        return null;
    }

    /**
     * The order a column of dates is written in: month first only when some
     * value can't be day-first (10/17/2026) and none can't be month-first.
     *
     * @param  iterable<mixed>  $values
     */
    public static function detectOrder(iterable $values): string
    {
        $dayFirst = false;
        $monthFirst = false;

        foreach ($values as $value) {
            if (is_string($value) && preg_match('/^\s*(\d{1,2})[-.\/](\d{1,2})[-.\/]\d{2,4}/', $value, $m)) {
                $dayFirst = $dayFirst || (int) $m[1] > 12;
                $monthFirst = $monthFirst || (int) $m[2] > 12;
            }
        }

        return $monthFirst && ! $dayFirst ? self::MONTH_FIRST : self::DAY_FIRST;
    }

    /** @return array{date: string, time: ?string}|null */
    private static function serial(float $serial): ?array
    {
        $days = (int) floor($serial);

        if ($days <= 0 || $days >= 2958466) {
            return null;
        }

        $minutes = (int) round(($serial - $days) * 1440);
        $moment = (new DateTimeImmutable('1899-12-30'))->modify("+{$days} days +{$minutes} minutes");

        return ['date' => $moment->format('Y-m-d'), 'time' => $minutes > 0 && $minutes < 1440 ? $moment->format('H:i') : null];
    }

    /** @return array{date: string, time: ?string}|null */
    private static function make(int $year, int $month, int $day, array $m): ?array
    {
        if ($year < 1900 || $year > 2200 || ! checkdate($month, $day, $year)) {
            return null;
        }

        $time = null;
        if (($m[4] ?? '') !== '') {
            $hour = (int) $m[4];
            $minute = (int) $m[5];
            $meridiem = strtolower($m[7] ?? '');

            if ($meridiem !== '') {
                if ($hour < 1 || $hour > 12) {
                    return null;
                }
                $hour = $hour % 12 + ($meridiem === 'pm' ? 12 : 0);
            }

            if ($hour > 23 || $minute > 59) {
                return null;
            }

            $time = sprintf('%02d:%02d', $hour, $minute);
        }

        return ['date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'time' => $time];
    }
}
