<?php

namespace App\Models;

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
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'data_table_id');
    }
}
