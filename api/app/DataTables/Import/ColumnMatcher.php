<?php

namespace App\DataTables\Import;

use App\DataTables\DataColumn;
use App\DataTables\Moment;
use App\DataTables\TableAnalytics;
use App\Enums\DataColumnType;

/**
 * Which file column goes into which table column, and, for a table made
 * from a file, what type each file column looks like.
 */
final class ColumnMatcher
{
    /**
     * Table column key → index of the file header with the same name
     * (ignoring case, spaces and punctuation), or null.
     *
     * @param  list<DataColumn>  $columns
     * @param  list<string>  $headers
     * @return array<string, ?int>
     */
    public static function suggest(array $columns, array $headers): array
    {
        $normalized = array_map(self::normalize(...), $headers);
        $used = [];
        $mapping = [];

        // Exact names first, then a name that contains the other.
        foreach ([true, false] as $exact) {
            foreach ($columns as $column) {
                if (isset($mapping[$column->key])) {
                    continue;
                }

                $label = self::normalize($column->label);
                foreach ($normalized as $index => $header) {
                    if (isset($used[$index]) || $header === '' || $label === '') {
                        continue;
                    }

                    $matches = $exact
                        ? $header === $label
                        : mb_strlen($label) >= 3 && mb_strlen($header) >= 3 && (str_contains($header, $label) || str_contains($label, $header));

                    if ($matches) {
                        $mapping[$column->key] = $index;
                        $used[$index] = true;
                        break;
                    }
                }
            }
        }

        return collect($columns)->mapWithKeys(fn (DataColumn $column) => [$column->key => $mapping[$column->key] ?? null])->all();
    }

    /**
     * Column definitions for a new table made from the file: the headers as
     * labels, and the type the values look like.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return list<array{label: string, type: string, required: bool, options: list<string>}>
     */
    public static function guessColumns(array $headers, array $rows): array
    {
        $sample = array_slice($rows, 0, 500);
        $columns = [];
        $labels = [];

        foreach ($headers as $index => $header) {
            $label = mb_substr($header, 0, 100);
            // Labels must be unique in a table.
            $base = $label;
            for ($n = 2; in_array(mb_strtolower($label), $labels, true); $n++) {
                $label = "{$base} {$n}";
            }
            $labels[] = mb_strtolower($label);

            $values = array_values(array_filter(array_column($sample, $index), fn ($v) => $v !== ''));
            [$type, $options] = self::guessType($header, $values);

            $columns[] = ['label' => $label, 'type' => $type->value, 'required' => false, 'options' => $options];
        }

        return $columns;
    }

    /** @return array{0: DataColumnType, 1: list<string>} */
    private static function guessType(string $header, array $values): array
    {
        if ($values === []) {
            return [DataColumnType::Text, []];
        }

        $order = Moment::detectOrder($values);
        $fits = function (DataColumnType $type, array $options = []) use ($values, $order): bool {
            $column = new DataColumn('x', 'x', $type, false, $options);
            foreach ($values as $value) {
                if ($column->parse($value, $order)[1] !== null) {
                    return false;
                }
            }

            return true;
        };

        // Dates first: plain numbers like 45000 would also pass as dates, so a
        // date column needs a separator in its values. With a time of day in
        // any value it's a date-and-time column (when an order was placed).
        $looksLikeDate = count(array_filter($values, fn ($v) => preg_match('/^\d{1,4}[.\/-]\d{1,2}[.\/-]\d{1,4}/', $v))) === count($values);
        if ($looksLikeDate && $fits(DataColumnType::Date)) {
            $withTime = array_filter($values, fn ($v) => (Moment::parse($v, $order)['time'] ?? null) !== null);

            return [$withTime === [] ? DataColumnType::Date : DataColumnType::DateTime, []];
        }

        // Phone numbers (+374 55 123456, 055123456) are digits, but not numbers.
        $phoneLike = array_filter($values, fn ($v) => preg_match('/^\s*(\+|0\d)/', $v));
        if ((TableAnalytics::isPersonal($header) && $phoneLike !== []) || count($phoneLike) > count($values) / 2) {
            return [DataColumnType::Text, []];
        }

        // Yes/no words (a column of only 0 and 1 stays a number).
        $lower = array_map(fn ($v) => mb_strtolower($v), $values);
        if (array_diff($lower, ['true', 'false', 'yes', 'no', 'այո', 'ոչ', 'да', 'нет', '1', '0']) === [] && ! ctype_digit(implode('', $lower))) {
            return [DataColumnType::Boolean, []];
        }

        if ($fits(DataColumnType::Number)) {
            $money = preg_match('/գին|գումար|արժեք|վճար|price|amount|sum|total|cost|revenue|цена|сумма|стоимость|выручка|оплата|֏|amd|руб/iu', $header)
                || array_filter($values, fn ($v) => preg_match('/֏|դր|amd|\$|€|₽/iu', $v));

            return [$money ? DataColumnType::Money : DataColumnType::Number, []];
        }

        // A handful of repeating values in a column named like a status, a
        // payment method, a source... Products or clients repeat too, but a
        // fixed list would refuse the next new one, so they stay text.
        $distinct = array_values(array_unique(array_map('trim', $values)));
        $listLike = preg_match('/կարգավիճակ|վճար|եղանակ|տեսակ|աղբյուր|ալիք|քաղաք|status|payment|method|type|source|channel|city|статус|оплат|способ|тип|источник|канал|город/iu', $header);
        if ($listLike && count($values) >= 5 && count($distinct) <= 10 && max(array_map('mb_strlen', $distinct)) <= 40) {
            sort($distinct);

            return [DataColumnType::Select, $distinct];
        }

        return [DataColumnType::Text, []];
    }

    private static function normalize(string $value): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)));
    }
}
