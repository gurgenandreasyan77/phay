<?php

namespace App\Models;

use App\DataTables\DataColumn;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table a business designed itself (see the create_data_tables
 * migration). `columns` is a list of {key, label, type, required, options}.
 * A column's `role` says what it means (date, price, quantity...), see
 * ColumnRole; RowMetrics reads a row's date and money by these roles.
 */
class DataTable extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section', 'name', 'description', 'columns', 'sort_order'];

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

    /** The column with this role (price, quantity...), if the table has one. */
    public function roleColumn(ColumnRole $role): ?DataColumn
    {
        return collect($this->columnDefinitions())->first(fn (DataColumn $c) => $c->role === $role);
    }

    /** When a row happened. */
    public function dateColumn(): ?DataColumn
    {
        return $this->roleColumn(ColumnRole::Date);
    }

    /**
     * What a row's money comes from: the total column, or else the price
     * (times the quantity, see RowMetrics).
     */
    public function amountColumn(): ?DataColumn
    {
        return $this->roleColumn(ColumnRole::Amount) ?? $this->roleColumn(ColumnRole::UnitPrice);
    }

    public function quantityColumn(): ?DataColumn
    {
        return $this->roleColumn(ColumnRole::Quantity);
    }

    /** "Գին × Քանակ", or the total column's name: how a row's amount is found. */
    public function amountLabel(): ?string
    {
        $total = $this->roleColumn(ColumnRole::Amount);
        $price = $this->roleColumn(ColumnRole::UnitPrice);
        $quantity = $this->quantityColumn();

        return match (true) {
            $total && $price && $quantity => "{$total->label} (կամ {$price->label} × {$quantity->label})",
            $total !== null => $total->label,
            $price && $quantity => "{$price->label} × {$quantity->label}",
            default => $price?->label,
        };
    }
}
