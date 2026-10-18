<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Rows and the employees who did them. A row is linked to an employee by
 * the name in its performer column (RowMetrics keeps the link on the row);
 * these keep the links right when the employees change: a new employee
 * gets the rows already carrying their name (a file imported before they
 * were added), a renamed one keeps their rows under the new name.
 */
final class Performers
{
    /**
     * The current business's tables with a performer column.
     *
     * @return Collection<int, DataTable>
     */
    public static function tables(): Collection
    {
        return DataTable::query()->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (DataTable $table) => $table->performerColumn() !== null)
            ->values();
    }

    /** Links the rows naming this employee that aren't linked to anyone yet. */
    public static function link(Employee $employee): void
    {
        $name = Employee::normalize($employee->name);

        foreach (self::tables() as $table) {
            $key = $table->performerColumn()->key;

            DataTableRow::query()
                ->where('data_table_id', $table->id)
                ->whereNull('employee_id')
                ->select(['id', 'data'])
                ->chunkById(500, function ($rows) use ($key, $name, $employee) {
                    $ids = $rows
                        ->filter(fn (DataTableRow $row) => is_string($v = $row->data[$key] ?? null) && Employee::normalize($v) === $name)
                        ->modelKeys();

                    if ($ids !== []) {
                        DataTableRow::query()->whereKey($ids)->update(['employee_id' => $employee->id]);
                    }
                });
        }
    }

    /** The employee's rows say their new name, then rows with the new name join them. */
    public static function rename(Employee $employee): void
    {
        $keys = self::tables()->mapWithKeys(fn (DataTable $t) => [$t->id => $t->performerColumn()->key]);

        DataTableRow::query()
            ->where('employee_id', $employee->id)
            ->select(['id', 'data_table_id', 'data'])
            ->chunkById(500, function ($rows) use ($keys, $employee) {
                foreach ($rows as $row) {
                    $key = $keys[$row->data_table_id] ?? null;
                    if ($key === null) {
                        continue;
                    }

                    // Not save(): the row's metrics don't change, only the name it shows.
                    DataTableRow::query()->whereKey($row->id)->update([
                        'data' => json_encode([...$row->data, $key => $employee->name], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });

        self::link($employee);
    }

    /**
     * Reads a row's cost: its unit cost × quantity (a row without a quantity
     * once), as the analytics count it; null when the cost isn't filled.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return callable(DataTableRow): (int|float|null)
     */
    public static function costReader(Collection $tables): callable
    {
        $keys = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => [
            'cost' => $t->roleColumn(ColumnRole::Cost)?->key,
            'quantity' => $t->quantityColumn()?->key,
        ]]);
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;

        return function (DataTableRow $row) use ($keys, $number) {
            $key = $keys[$row->data_table_id] ?? ['cost' => null, 'quantity' => null];
            $cost = $key['cost'] ? $number($row->data[$key['cost']] ?? null) : null;

            if ($cost === null) {
                return null;
            }

            $quantity = $key['quantity'] ? $number($row->data[$key['quantity']] ?? null) : null;

            return Decimal::round(Decimal::multiply($cost, $quantity ?? 1));
        };
    }
}
