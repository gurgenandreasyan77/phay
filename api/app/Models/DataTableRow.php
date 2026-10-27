<?php

namespace App\Models;

use App\DataTables\Duplicates\RowDuplicates;
use App\DataTables\RowMetrics;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTableRow extends Model
{
    use BelongsToTenant;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORT = 'import';

    /** Made from a Google Calendar event (external_id is the event's id). */
    public const SOURCE_GOOGLE = 'google';

    protected $fillable = ['data_table_id', 'data', 'source', 'data_import_id', 'created_by_id', 'external_id', 'external_hash'];

    protected $casts = [
        'data' => 'array',
        'amount' => 'float',
        'employee_id' => 'integer',
        'duplicate_of_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // occurred_at, amount and employee_id always follow the row's data.
        static::saving(function (DataTableRow $row) {
            if ($row->isDirty('data') || ! $row->exists) {
                $row->forceFill(RowMetrics::for($row->getRelationValue('table'))($row->data ?? []));
            }
        });

        // The same record in another table is counted once (RowDuplicates).
        static::saved(function (DataTableRow $row) {
            if ($row->wasRecentlyCreated || $row->wasChanged(['data', 'occurred_at', 'amount', 'data_table_id'])) {
                app(RowDuplicates::class)->touched($row);
            }
        });
        static::deleted(fn (DataTableRow $row) => app(RowDuplicates::class)->touched($row));
    }

    /**
     * Rows that count: not a copy of a row of another table (the same
     * order written twice, see RowDuplicates). The calendar, analytics,
     * finance and the AI read only these; a table's own page shows all.
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('duplicate_of_id'));
    }

    /** The row of another table this one is a copy of. */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'data_table_id');
    }

    /** Who did it: the employee named in the performer column. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
