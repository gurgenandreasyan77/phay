<?php

namespace App\Enums;

/**
 * What stretch of time a business's table is kept for: a table a day, a
 * week, a month, a quarter or a year, or one table for any dates (custom,
 * e.g. a table covering August to October). A table of a period holds the
 * rows of its period: an order dated in another one goes to that period's
 * table (see PeriodTables).
 */
enum TablePeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Օրական',
            self::Week => 'Շաբաթական',
            self::Month => 'Ամսական',
            self::Quarter => 'Եռամսյակային',
            self::Year => 'Տարեկան',
            self::Custom => 'Ազատ ժամանակահատված',
        };
    }

    /** A period of time a row can fall in; custom isn't one. */
    public function isCalendar(): bool
    {
        return $this !== self::Custom;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function calendarValues(): array
    {
        return array_values(array_diff(self::values(), [self::Custom->value]));
    }
}
