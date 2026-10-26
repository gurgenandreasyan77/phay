<?php

namespace App\Models;

use App\DataTables\CurrentTableScope;
use App\DataTables\DataColumn;
use App\DataTables\Period;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** A table made from the business's Google calendars (App\Google\CalendarTables). */
    public const SOURCE_GOOGLE = 'google';

    protected $fillable = ['section', 'name', 'description', 'columns', 'period', 'period_start', 'sort_order', 'source', 'archived_at', 'replaced_by_id', 'reshape_id'];

    protected $casts = [
        'section' => DataTableSection::class,
        'columns' => 'array',
        'period' => TablePeriod::class,
        'period_start' => 'immutable_date',
        'sort_order' => 'integer',
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTableScope);
    }

    /** Older versions too: for the tables list and a table's own page. */
    public static function withArchived(): Builder
    {
        return static::withoutGlobalScope(CurrentTableScope::class);
    }

    /** A table's page and rows stay reachable after a newer version replaced it. */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withArchived()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /** The version that replaced this one. */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id')->withoutGlobalScope(CurrentTableScope::class);
    }

    /**
     * The month ("Y-m") read from the name or rows of a table whose period
     * isn't chosen (see PeriodTables::infer); not stored.
     */
    public ?string $inferredMonth = null;

    /**
     * The period the table is kept for: the chosen one, else the month its
     * name or rows say (when inferred); null for a table of any dates.
     */
    public function period(): ?Period
    {
        if ($this->period !== null) {
            return $this->period->isCalendar() && $this->period_start !== null
                ? Period::of($this->period, $this->period_start)
                : null;
        }

        return $this->inferredMonth !== null ? Period::of(TablePeriod::Month, $this->inferredMonth.'-01') : null;
    }

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

    /** Who did the row (an employee's name). */
    public function performerColumn(): ?DataColumn
    {
        return $this->roleColumn(ColumnRole::Performer);
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
