<?php

namespace App\Exports;

use App\DataTables\DataColumn;
use App\Enums\DataColumnType;
use App\Models\DataTable;
use App\Models\DataTableRow;

/**
 * A business's own table as a sheet: its columns as they are named, its
 * rows by date (rows without one last), values as typed. The file can be
 * imported back as it is.
 */
final class TableExport
{
    /** Rows one table's sheet holds at most. */
    public const MAX_ROWS = 100000;

    public static function sheet(DataTable $table): Sheet
    {
        $columns = $table->columnDefinitions();

        return new Sheet(
            $table->name,
            array_map(fn (DataColumn $c) => Sheet::column($c->label, self::type($c->type)), $columns),
            self::rows($table, $columns),
        );
    }

    /**
     * @param  list<DataColumn>  $columns
     * @return \Generator<list<mixed>>
     */
    private static function rows(DataTable $table, array $columns): \Generator
    {
        $rows = DataTableRow::query()
            ->where('data_table_id', $table->id)
            ->orderByRaw('CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->select(['id', 'data'])
            ->lazy(2000);

        foreach ($rows as $row) {
            $data = $row->data ?? [];
            yield array_map(fn (DataColumn $c) => $data[$c->key] ?? null, $columns);
        }
    }

    private static function type(DataColumnType $type): string
    {
        return match ($type) {
            DataColumnType::Number => Sheet::NUMBER,
            DataColumnType::Money => Sheet::MONEY,
            DataColumnType::Date => Sheet::DATE,
            DataColumnType::DateTime => Sheet::DATETIME,
            DataColumnType::Boolean => Sheet::BOOLEAN,
            default => Sheet::TEXT,
        };
    }
}
