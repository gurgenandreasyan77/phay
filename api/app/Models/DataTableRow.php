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

    protected $fillable = ['data_table_id', 'data', 'source', 'data_import_id', 'created_by_id'];

    protected $casts = [
        'data' => 'array',
        'amount' => 'float',
    ];

    protected static function booted(): void
    {
        // occurred_at and amount always follow the row's data.
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
}
