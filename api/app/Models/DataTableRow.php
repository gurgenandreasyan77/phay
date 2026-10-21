<?php

namespace App\Models;

use App\DataTables\RowMetrics;
use App\Models\Concerns\BelongsToTenant;
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
    ];

    protected static function booted(): void
    {
        // occurred_at, amount and employee_id always follow the row's data.
        static::saving(function (DataTableRow $row) {
            if ($row->isDirty('data') || ! $row->exists) {
                $row->forceFill(RowMetrics::for($row->getRelationValue('table'))($row->data ?? []));
            }
        });
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
