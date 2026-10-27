<?php

namespace App\DataTables\Import;

use App\DataTables\Changes\TableChanges;
use App\DataTables\DataColumn;
use App\DataTables\Moment;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\TableChange;
use App\Models\User;
use InvalidArgumentException;

/**
 * A new table made like one the owner has elsewhere (a Google Sheet, a file,
 * a page): the same columns in the same order, each with the type and
 * meaning its header and values suggest (ColumnMatcher::guessColumns),
 * and all its rows, as they are. Only planned: the owner applies the card.
 *
 * A cell its column can't take (a word in a number column) is left empty
 * and counted, never refused: the copy keeps every row. The model may
 * correct a column's label, type or meaning, or leave columns out.
 */
final class TableCopier
{
    /** Rows copied at most. */
    public const MAX_ROWS = 1000;

    public function __construct(private readonly TableChanges $changes) {}

    /**
     * The rows as the guess sees them: in a column of numbers (two thirds of its cells), a stray word
     * («չգիտեմ», «—») doesn't make the whole column text. It is left empty
     * by the copy, and counted.
     *
     * @param  list<list<string>>  $rows
     * @return list<list<string>>
     */
    private static function forGuessing(array $rows): array
    {
        $number = fn (string $v) => preg_match('/^[-+]?[\d\s.,\x{00A0}]+\s*(դր\.?|֏|amd|руб\.?|\$|€)?$/iu', trim($v)) === 1;
        $width = max(array_map('count', $rows) ?: [0]);
        for ($i = 0; $i < $width; $i++) {
            $filled = array_filter(array_column($rows, $i), fn ($v) => trim((string) $v) !== '');
            $numbers = count(array_filter($filled, fn ($v) => $number((string) $v)));
            if ($numbers === 0 || $numbers === count($filled) || $numbers < 2 || $numbers < 0.66 * count($filled)) {
                continue;
            }
            foreach ($rows as $r => $cells) {
                if (isset($cells[$i]) && trim($cells[$i]) !== '' && ! $number($cells[$i])) {
                    $rows[$r][$i] = '';
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array{sheet: Sheet, name: ?string, from: string}  $source
     * @param  array<string, mixed>  $input  section, name, description, period, period_date, columns (overrides by header), skip (headers)
     * @return array{change: TableChange, columns: list<array>, rows: int, emptied: array<string, int>, truncated: bool}
     */
    public function plan(array $source, array $input, ?User $user): array
    {
        $sheet = $source['sheet'];
        $section = DataTableSection::tryFrom((string) ($input['section'] ?? ''))
            ?? throw new InvalidArgumentException('section is required: orders, sales or expenses.');
        if ($sheet->rows === [] && $sheet->headers === []) {
            throw new InvalidArgumentException('The table is empty.');
        }

        $skip = array_map(fn ($h) => mb_strtolower(trim((string) $h)), (array) ($input['skip'] ?? []));
        $overrides = [];
        foreach ((array) ($input['columns'] ?? []) as $override) {
            $overrides[mb_strtolower(trim((string) ($override['from'] ?? '')))] = (array) $override;
        }

        $guessed = ColumnMatcher::guessColumns($sheet->headers, self::forGuessing($sheet->rows), $section);
        $columns = [];
        $indexes = [];
        foreach ($guessed as $i => $column) {
            $header = mb_strtolower($sheet->headers[$i]);
            if (in_array($header, $skip, true)) {
                continue;
            }
            if (isset($overrides[$header])) {
                $o = $overrides[$header];
                $column['label'] = isset($o['label']) && trim((string) $o['label']) !== '' ? mb_substr(trim((string) $o['label']), 0, 100) : $column['label'];
                $column['type'] = isset($o['type']) && DataColumnType::tryFrom((string) $o['type']) ? (string) $o['type'] : $column['type'];
                if (array_key_exists('role', $o)) {
                    $column['role'] = $o['role'] === null || $o['role'] === '' ? null : (ColumnRole::tryFrom((string) $o['role'])?->value ?? $column['role']);
                }
                if (isset($o['options']) && is_array($o['options'])) {
                    $column['options'] = array_values(array_map('strval', $o['options']));
                }
            }
            // A role chosen twice: the first column keeps it.
            if ($column['role'] !== null && in_array($column['role'], array_column($columns, 'role'), true)) {
                $column['role'] = null;
            }
            $columns[] = $column;
            $indexes[] = $i;
        }
        if ($columns === []) {
            throw new InvalidArgumentException('Every column was left out.');
        }

        // Each cell as its column takes it; what it can't take stays empty.
        $definitions = array_map(fn (array $c, int $n) => DataColumn::fromArray([...$c, 'key' => 'c'.$n, 'required' => false]), $columns, array_keys($columns));
        // 10/09/2026: day or month first, as most of the column's dates say.
        $orders = array_map(fn (int $i) => Moment::detectOrder(array_column($sheet->rows, $i)), $indexes);
        $rows = [];
        $emptied = [];
        foreach (array_slice($sheet->rows, 0, self::MAX_ROWS) as $cells) {
            $values = [];
            foreach ($definitions as $n => $column) {
                $raw = $cells[$indexes[$n]] ?? '';
                if (trim($raw) === '') {
                    continue;
                }
                [$value, $error] = $column->parse($raw, $orders[$n]);
                if ($error !== null) {
                    $emptied[$column->label] = ($emptied[$column->label] ?? 0) + 1;

                    continue;
                }
                // Dates and numbers go on as the column reads them back.
                $values[$column->label] = $value;
            }
            if ($values !== []) {
                $rows[] = $values;
            }
        }

        $name = trim((string) ($input['name'] ?? '')) ?: ($source['name'] ?? 'Պատճեն');
        $change = $this->changes->planCreate([
            'section' => $section->value,
            'name' => mb_substr($name, 0, 100),
            'description' => $input['description'] ?? "Պատճենված՝ {$source['from']}",
            'period' => $input['period'] ?? null,
            'period_date' => $input['period_date'] ?? null,
            'columns' => $columns,
            'rows' => $rows,
        ], $user, self::MAX_ROWS, dateToday: false);

        return [
            'change' => $change,
            'columns' => array_map(fn (array $c, int $i) => array_filter([
                'from' => $sheet->headers[$i],
                'label' => $c['label'],
                'type' => $c['type'],
                'meaning' => $c['role'],
            ], fn ($v) => $v !== null), $columns, $indexes),
            'rows' => count($rows),
            'emptied' => $emptied,
            'truncated' => $sheet->truncated || count($sheet->rows) > self::MAX_ROWS,
        ];
    }
}
