<?php

namespace App\DataTables\Import;

use App\DataTables\ColumnValues;
use App\DataTables\DataColumn;
use App\DataTables\Moment;
use App\DataTables\TableAnalytics;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;

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
     * @param  list<list<string>>  $rows  the file's rows: a header that says nothing is read by its values
     * @return array<string, ?int>
     */
    public static function suggest(array $columns, array $headers, array $rows = []): array
    {
        $normalized = array_map(self::normalize(...), $headers);
        $used = [];
        $mapping = [];

        // Exact names first, then a name that contains the other, then the
        // meaning: a "Цена" header goes into the price column called "Գին".
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

        foreach ($columns as $column) {
            if (isset($mapping[$column->key]) || ! $column->role) {
                continue;
            }
            foreach ($headers as $index => $header) {
                if (! isset($used[$index]) && ColumnRole::guess($header) === $column->role) {
                    $mapping[$column->key] = $index;
                    $used[$index] = true;
                    break;
                }
            }
        }

        // A "Column 3" of "iPhone 15" values goes into the model column.
        $sample = array_slice($rows, 0, 200);
        foreach ($columns as $column) {
            if (isset($mapping[$column->key]) || ! $column->role || $sample === []) {
                continue;
            }
            foreach ($headers as $index => $header) {
                if (! isset($used[$index]) && ColumnRole::guess($header) === null && ColumnValues::guess(array_column($sample, $index)) === $column->role) {
                    $mapping[$column->key] = $index;
                    $used[$index] = true;
                    break;
                }
            }
        }

        return collect($columns)->mapWithKeys(fn (DataColumn $column) => [$column->key => $mapping[$column->key] ?? null])->all();
    }

    /**
     * Column definitions for a new table made from the file: the headers as
     * labels, the type the values look like, and the role (price, quantity,
     * date...) the header's words suggest, or else its values (ColumnValues),
     * each role once.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return list<array{label: string, type: string, required: bool, options: list<string>, role: ?string}>
     */
    public static function guessColumns(array $headers, array $rows, ?DataTableSection $section = null): array
    {
        $sample = array_slice($rows, 0, 500);
        $columns = [];
        $labels = [];
        $roles = [];

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
            [$type, $options, $role] = self::fitRole(self::roleOf($header, $values, $type, $section, $roles), $type, $options, $values, $roles);
            if ($role) {
                $roles[$role->value] = true;
            }

            $columns[] = ['label' => $label, 'type' => $type->value, 'required' => false, 'options' => $options, 'role' => $role?->value];
        }

        return $columns;
    }

    /**
     * The role of a file column: by its header's words, else by its values,
     * else a date column is the date (when no other column is).
     *
     * @param  array<string, true>  $taken
     */
    public static function roleOf(string $header, array $values, DataColumnType $type, ?DataTableSection $section, array $taken): ?ColumnRole
    {
        $role = ColumnRole::guess($header);
        if ($role && ! isset($taken[$role->value])) {
            return $role;
        }

        $role = ColumnValues::guess($values, $section, $taken);
        if ($role) {
            return $role;
        }

        return in_array($type, [DataColumnType::Date, DataColumnType::DateTime], true) && ! isset($taken[ColumnRole::Date->value]) ? ColumnRole::Date : null;
    }

    /**
     * The guessed type, made to fit the role the header suggests: a price
     * is money, a payment method a list; a role the values don't fit (a
     * "Date" column of words) or already taken is dropped.
     *
     * @param  array<string, true>  $taken
     * @return array{0: DataColumnType, 1: list<string>, 2: ?ColumnRole}
     */
    private static function fitRole(?ColumnRole $role, DataColumnType $type, array $options, array $values, array $taken): array
    {
        if (! $role || isset($taken[$role->value])) {
            return [$type, $options, null];
        }

        $allowed = $role->types();

        if ($allowed[0] === DataColumnType::Money && $type === DataColumnType::Number) {
            return [DataColumnType::Money, [], $role];
        }

        $distinct = array_values(array_unique(array_map('trim', $values)));
        if ($allowed[0] === DataColumnType::Select && $type === DataColumnType::Text && $distinct !== [] && count($distinct) <= 20 && max(array_map('mb_strlen', $distinct)) <= 40) {
            sort($distinct);

            return [DataColumnType::Select, $distinct, $role];
        }

        if (in_array($type, $allowed, true)) {
            return [$type, $options, $role];
        }

        // Names, phones, notes: whatever the values look like, they're text.
        if ($allowed[0] === DataColumnType::Text) {
            return [DataColumnType::Text, [], $role];
        }

        return [$type, $options, null];
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
