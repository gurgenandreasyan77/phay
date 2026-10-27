<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;

/**
 * When a row of a business's own table happened and what it's worth, read
 * from its data by the table's date and amount columns and kept on the row
 * (occurred_at, amount), so analytics and the AI assistant can sum and
 * group rows of every table in the database. Columns are found by their
 * role (ColumnRole), not by their names. Who did the row is kept the same
 * way (employee_id): the employee named in its performer column.
 */
final class RowMetrics
{
    /**
     * @return callable(array): array{occurred_at: ?string, amount: int|float|null, employee_id: ?int}
     */
    public static function for(DataTable $table): callable
    {
        $date = $table->dateColumn();
        $total = $table->roleColumn(ColumnRole::Amount);
        $price = $table->roleColumn(ColumnRole::UnitPrice);
        $quantity = $table->quantityColumn();
        $discount = $table->roleColumn(ColumnRole::Discount);
        $percent = $table->roleColumn(ColumnRole::DiscountPercent);
        $delivery = $table->roleColumn(ColumnRole::DeliveryFee);
        $time = $table->roleColumn(ColumnRole::Time);
        $performer = $table->performerColumn();
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;
        $employees = $performer ? self::employees($table->tenant_id) : [];

        return function (array $data) use ($date, $total, $price, $quantity, $discount, $percent, $delivery, $time, $performer, $employees, $number): array {
            $moment = $date ? ($data[$date->key] ?? null) : null;
            // A date without a time and the time in a column of its own ("14:30").
            if ($time && is_string($moment) && strlen($moment) === 10 && is_string($clock = $data[$time->key] ?? null)
                && preg_match('/^\s*(\d{1,2})[:.](\d{2})/', $clock, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
                $moment .= sprintf(' %02d:%02d', $m[1], $m[2]);
            }

            // The row's own total (the final price) when it has one; else price
            // × quantity (0.5 kg at 3000 a kg is 1500), a row without a quantity
            // once, less its discount (in money or in percent), plus delivery.
            $amount = $total ? $number($data[$total->key] ?? null) : null;
            if ($amount === null && $price && ($unitPrice = $number($data[$price->key] ?? null)) !== null) {
                $amount = Decimal::multiply($unitPrice, ($quantity ? $number($data[$quantity->key] ?? null) : null) ?? 1);
                if ($percent && ($off = $number($data[$percent->key] ?? null)) !== null && $off > 0 && $off <= 100) {
                    $amount = Decimal::multiply($amount, (100 - $off) / 100);
                }
                if ($discount && ($off = $number($data[$discount->key] ?? null)) !== null) {
                    $amount -= abs($off);
                }
                if ($delivery && ($fee = $number($data[$delivery->key] ?? null)) !== null) {
                    $amount += $fee;
                }
            }

            return [
                'occurred_at' => is_string($moment) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $moment)
                    ? (strlen($moment) === 10 ? "{$moment} 00:00:00" : "{$moment}:00")
                    : null,
                'amount' => $amount === null ? null : Decimal::round($amount),
                'employee_id' => $performer && is_string($name = $data[$performer->key] ?? null)
                    ? ($employees[Employee::normalize($name)] ?? null)
                    : null,
            ];
        };
    }

    /**
     * The business's employees by their normalized name.
     *
     * @return array<string, int>
     */
    public static function employees(?int $tenantId): array
    {
        return Employee::withoutTenancy()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'name'])
            // Two employees of one name: the first keeps the rows.
            ->reverse()
            ->mapWithKeys(fn (Employee $e) => [Employee::normalize($e->name) => $e->id])
            ->all();
    }

    /** Reads every row of the table again, after its columns or choices changed. */
    public static function recompute(DataTable $table): void
    {
        $metrics = self::for($table);

        DataTableRow::withoutTenancy()
            ->where('data_table_id', $table->id)
            ->select(['id', 'data', 'occurred_at', 'amount', 'employee_id'])
            ->chunkById(500, function ($rows) use ($metrics) {
                foreach ($rows as $row) {
                    $values = $metrics($row->data ?? []);

                    if ($row->getRawOriginal('occurred_at') !== $values['occurred_at'] || (float) $row->amount !== (float) $values['amount'] || ($row->amount === null) !== ($values['amount'] === null) || $row->employee_id !== $values['employee_id']) {
                        DataTableRow::withoutTenancy()->whereKey($row->id)->update($values);
                    }
                }
            });

        // Other dates or amounts may make other copies (or none).
        if (! Duplicates\RowDuplicates::isPaused()) {
            app(Duplicates\RowDuplicates::class)->refresh();
        }
    }
}
