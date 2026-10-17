<?php

namespace App\Enums;

/**
 * The kind of value a column of a custom table holds. Values are stored in
 * JSON as: text/select as strings, number/money as numbers, date as
 * Y-m-d, boolean as true/false.
 */
enum DataColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case Select = 'select';
    case Boolean = 'boolean';

    public function isNumeric(): bool
    {
        return $this === self::Number || $this === self::Money;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
