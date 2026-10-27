<?php

namespace App\Models;

use App\Enums\DataTableSection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change in the business's tables an AI agent prepared
 * (App\DataTables\Changes\TableChanges): planned (waiting for the owner's
 * press), applied, undone, or discarded.
 */
class TableChange extends Model
{
    use BelongsToTenant;

    public const PLANNED = 'planned';

    public const APPLIED = 'applied';

    public const UNDONE = 'undone';

    public const DISCARDED = 'discarded';

    public const CREATE_TABLE = 'create_table';

    public const EDIT_TABLE = 'edit_table';

    public const DELETE_TABLES = 'delete_tables';

    public const ADD_ROWS = 'add_rows';

    public const UPDATE_ROWS = 'update_rows';

    public const DELETE_ROWS = 'delete_rows';

    protected $fillable = ['user_id', 'kind', 'section', 'data_table_id', 'payload', 'result', 'status', 'applied_at', 'undone_at'];

    protected $casts = [
        'section' => DataTableSection::class,
        'payload' => 'array',
        'result' => 'array',
        'applied_at' => 'immutable_datetime',
        'undone_at' => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The table it changes, archived or not. */
    public function table(): ?DataTable
    {
        return $this->data_table_id ? DataTable::withArchived()->find($this->data_table_id) : null;
    }
}
