<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An uploaded file and its import into a business's own table.
 * Status: uploaded (read, waiting for the column mapping), done, undone.
 */
class DataImport extends Model
{
    use BelongsToTenant;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_DONE = 'done';

    public const STATUS_UNDONE = 'undone';

    protected $fillable = [
        'data_table_id', 'user_id', 'file_name', 'format', 'path', 'headers', 'total_rows',
        'status', 'mapping', 'imported_rows', 'skipped_rows', 'errors',
    ];

    protected $casts = [
        'headers' => 'array',
        'mapping' => 'array',
        'errors' => 'array',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'skipped_rows' => 'integer',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'data_table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class);
    }
}
