<?php

namespace App\DataTables;

use App\Models\DataTable;
use App\Models\DataTableRow;

/**
 * When a row of a business's own table happened and what it's worth, read
 * from its data by the table's date and amount columns and kept on the row
 * (occurred_at, amount), so analytics and the AI assistant can sum and
 * group rows of every table in the database.
 */
final class RowMetrics
{
    /**
     * @return callable(array): array{occurred_at: ?string, amount: int|float|null}
     */
    public static function for(DataTable $table): callable
    {
        $date = $table->dateColumn();
        $amount = $table->amountColumn();
        $quantity = $table->quantityColumn();
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;

        return function (array $data) use ($date, $amount, $quantity, $number): array {
            $moment = $date ? ($data[$date->key] ?? null) : null;
            $value = $amount ? $number($data[$amount->key] ?? null) : null;

            if ($value !== null && $quantity) {
                // A row without a quantity counts once.
                $value *= $number($data[$quantity->key] ?? null) ?? 1;
            }

            return [
                'occurred_at' => is_string($moment) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $moment)
                    ? (strlen($moment) === 10 ? "{$moment} 00:00:00" : "{$moment}:00")
                    : null,
                'amount' => $value === null ? null : round($value, 2),
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
