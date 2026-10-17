<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Models\DataTable;
use App\Models\DataTableRow;

/**
 * When a row of a business's own table happened and what it's worth, read
 * from its data by the table's date and amount columns and kept on the row
 * (occurred_at, amount), so analytics and the AI assistant can sum and
 * group rows of every table in the database. Columns are found by their
 * role (ColumnRole), not by their names.
 */
final class RowMetrics
{
    /**
     * @return callable(array): array{occurred_at: ?string, amount: int|float|null}
     */
    public static function for(DataTable $table): callable
    {
        $date = $table->dateColumn();
        $total = $table->roleColumn(ColumnRole::Amount);
        $price = $table->roleColumn(ColumnRole::UnitPrice);
        $quantity = $table->quantityColumn();
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;

        return function (array $data) use ($date, $total, $price, $quantity, $number): array {
            $moment = $date ? ($data[$date->key] ?? null) : null;

            // The row's own total when it has one; else price × quantity
            // (0.5 kg at 3000 a kg is 1500), a row without a quantity once.
            $amount = $total ? $number($data[$total->key] ?? null) : null;
            if ($amount === null && $price && ($unitPrice = $number($data[$price->key] ?? null)) !== null) {
                $amount = Decimal::multiply($unitPrice, ($quantity ? $number($data[$quantity->key] ?? null) : null) ?? 1);
            }

            return [
                'occurred_at' => is_string($moment) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $moment)
                    ? (strlen($moment) === 10 ? "{$moment} 00:00:00" : "{$moment}:00")
                    : null,
                'amount' => $amount === null ? null : Decimal::round($amount),
            ];
        };
    }

    /** Reads every row of the table again, after its columns or choices changed. */
    public static function recompute(DataTable $table): void
    {
        $metrics = self::for($table);

        DataTableRow::withoutTenancy()
            ->where('data_table_id', $table->id)
            ->select(['id', 'data', 'occurred_at', 'amount'])
            ->chunkById(500, function ($rows) use ($metrics) {
                foreach ($rows as $row) {
                    $values = $metrics($row->data ?? []);

                    if ($row->getRawOriginal('occurred_at') !== $values['occurred_at'] || (float) $row->amount !== (float) $values['amount'] || ($row->amount === null) !== ($values['amount'] === null)) {
                        DataTableRow::withoutTenancy()->whereKey($row->id)->update($values);
                    }
                }
            });
    }
}
