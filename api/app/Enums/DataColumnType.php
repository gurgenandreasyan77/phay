<?php

namespace App\Enums;

/**
 * The kind of value a column of a custom table holds. Values are stored in
 * JSON as: text/select as strings, number/money as numbers, date as
 * Y-m-d, datetime as "Y-m-d H:i" (the business's local time), boolean as
 * true/false.
 */
enum DataColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case DateTime = 'datetime';
    case Select = 'select';
    case Boolean = 'boolean';

    public function isNumeric(): bool
    {
        return $this === self::Number || $this === self::Money;
    }

    /** A date, with or without the time: what a row's moment can come from. */
    public function isTemporal(): bool
    {
        return $this === self::Date || $this === self::DateTime;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
