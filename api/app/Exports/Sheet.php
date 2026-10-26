<?php

namespace App\Exports;

/**
 * One sheet of a file the admin downloads: its columns (a label and what
 * kind of values: text, number, money, date, datetime, percent, boolean)
 * and its rows of plain values (dates as "Y-m-d" or "Y-m-d H:i"). A total
 * row at the end is written in bold.
 */
final readonly class Sheet
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const PERCENT = 'percent';

    public const BOOLEAN = 'boolean';

    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  iterable<list<mixed>>  $rows
     * @param  ?list<mixed>  $total
     */
    public function __construct(
        public string $name,
        public array $columns,
        public iterable $rows,
        public ?array $total = null,
    ) {}

    /** @return array{label: string, type: string} */
    public static function column(string $label, string $type = self::TEXT): array
    {
        return ['label' => $label, 'type' => $type];
    }
}
