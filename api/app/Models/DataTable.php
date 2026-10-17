<?php

namespace App\Models;

use App\DataTables\DataColumn;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table a business designed itself (see the create_data_tables
 * migration). `columns` is a list of {key, label, type, required, options}.
 * date_column, amount_column and quantity_column name (by key) the columns
 * that say when a row happened and what it's worth; see RowMetrics.
 */
class DataTable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section', 'name', 'description', 'columns', 'date_column', 'amount_column', 'quantity_column', 'sort_order'];

    /** Number columns that hold a quantity, so a row is worth price × quantity. */
    public const QUANTITY_LABELS = '/քանակ|հատ|qty|quantity|pcs|count|кол-?во|количество|шт/iu';

    protected $casts = [
        'section' => DataTableSection::class,
        'columns' => 'array',
        'sort_order' => 'integer',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(DataImport::class);
    }

    /** @return list<DataColumn> */
    public function columnDefinitions(): array
    {
        return array_map(DataColumn::fromArray(...), $this->columns ?? []);
    }

    public function column(?string $key): ?DataColumn
    {
        return $key === null ? null : collect($this->columnDefinitions())->first(fn (DataColumn $c) => $c->key === $key);
    }

    /** When a row happened: a date or date-and-time column. */
    public function dateColumn(): ?DataColumn
    {
        $column = $this->column($this->date_column);

        return $column?->type->isTemporal() ? $column : null;
    }

    /** What a row is worth: a money (or number) column, times the quantity column if any. */
    public function amountColumn(): ?DataColumn
    {
        $column = $this->column($this->amount_column);

        return $column?->type->isNumeric() ? $column : null;
    }

    public function quantityColumn(): ?DataColumn
    {
        $column = $this->column($this->quantity_column);

        return $column?->type === DataColumnType::Number && $column->key !== $this->amount_column ? $column : null;
    }

    /**
     * Picks the date, amount and quantity columns where none is chosen (or
     * the chosen one is gone): the first date-and-time or date column, the
     * first money column, and a number column named like a quantity.
     *
     * @param  list<string>  $explicit  fields the person set (even to none), left as they are
     */
    public function fillMetricColumns(array $explicit = []): void
    {
        $columns = collect($this->columnDefinitions());

        if (! in_array('date_column', $explicit, true) && ! $this->dateColumn()) {
            $this->date_column = ($columns->first(fn (DataColumn $c) => $c->type === DataColumnType::DateTime)
                ?? $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Date))?->key;
        }

        if (! in_array('amount_column', $explicit, true) && ! $this->amountColumn()) {
            $this->amount_column = $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Money)?->key;
        }

        if (! in_array('quantity_column', $explicit, true) && ! $this->quantityColumn()) {
            $this->quantity_column = $this->amount_column
                ? $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Number && preg_match(self::QUANTITY_LABELS, $c->label))?->key
                : null;
        }

        // A choice that no longer fits (column removed or retyped) is dropped.
        $this->date_column = $this->dateColumn()?->key;
        $this->amount_column = $this->amountColumn()?->key;
        $this->quantity_column = $this->quantityColumn()?->key;
    }
}
