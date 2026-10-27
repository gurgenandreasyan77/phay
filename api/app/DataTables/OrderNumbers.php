<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Models\DataTable;
use App\Models\DataTableRow;

/**
 * The number of a new order (or sale, a receipt...) left empty: the next
 * one after the biggest of its section's tables, in the same style. After
 * "SR-0041" comes "SR-0042", after 107 comes 108, and the first one is 1.
 * A number typed in is kept as it is.
 */
final class OrderNumbers
{
    /**
     * The values with an empty order number of the table filled: one after
     * another for several new rows.
     *
     * @param  list<array<string, mixed>>  $rows  values by column key
     * @return list<array<string, mixed>>
     */
    public static function fill(DataTable $table, array $rows): array
    {
        $column = $table->roleColumn(ColumnRole::OrderNumber);
        if (! $column) {
            return $rows;
        }

        $next = null;
        foreach ($rows as $i => $values) {
            if (trim((string) ($values[$column->key] ?? '')) !== '') {
                continue;
            }
            $next = $next === null ? self::next($table) : self::after($next);
            $rows[$i][$column->key] = $column->type === DataColumnType::Number ? (int) $next : $next;
        }

        return $rows;
    }

    /** The next number for a new row of the table's section, or null when the table keeps none. */
    public static function next(DataTable $table): ?string
    {
        if (! $table->roleColumn(ColumnRole::OrderNumber)) {
            return null;
        }

        // Every current table of the section that numbers its rows, with the number's column.
        $keys = DataTable::query()->where('section', $table->section)->get()
            ->push($table)
            ->mapWithKeys(fn (DataTable $t) => [$t->id => $t->roleColumn(ColumnRole::OrderNumber)?->key])
            ->filter();

        $best = null;
        DataTableRow::query()->whereIn('data_table_id', $keys->keys())->select(['id', 'data_table_id', 'data'])
            ->lazyById(1000)
            ->each(function (DataTableRow $row) use ($keys, &$best) {
                $value = $row->data[$keys[$row->data_table_id]] ?? null;
                $parts = is_scalar($value) ? self::parts((string) $value) : null;
                if ($parts && ($best === null || $parts[1] > $best[1] || ($parts[1] === $best[1] && strlen($parts[2]) > strlen($best[2])))) {
                    $best = $parts;
                }
            });

        return $best === null ? '1' : self::after($best[0].$best[2].$best[3]);
    }

    /** The number after this one, in its style: "SR-009" → "SR-010". */
    public static function after(string $number): string
    {
        $parts = self::parts($number);
        if (! $parts) {
            return $number.'-2';
        }
        [$prefix, $value, $digits, $suffix] = $parts;

        return $prefix.str_pad((string) ($value + 1), strlen($digits), '0', STR_PAD_LEFT).$suffix;
    }

    /**
     * The text before the last digits, their value, the digits as written and the text after.
     *
     * @return array{0: string, 1: int, 2: string, 3: string}|null
     */
    private static function parts(string $number): ?array
    {
        $number = trim($number);
        // A whole number from a number column comes back as "12.0" from some files.
        if (preg_match('/^(\d+)\.0+$/', $number, $m)) {
            $number = $m[1];
        }
        if (strlen($number) > 40 || ! preg_match('/^(.*?)(\d{1,15})(\D*)$/u', $number, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2], $m[2], $m[3]];
    }
}
