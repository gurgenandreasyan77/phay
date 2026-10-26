<?php

namespace App\Models;

use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tables split into periods or put together (App\DataTables\Reshape\TableReshaper):
 * planned (by an AI agent, waiting for the owner), applied, or undone.
 */
class TableReshape extends Model
{
    use BelongsToTenant;

    public const PLANNED = 'planned';

    public const APPLIED = 'applied';

    public const UNDONE = 'undone';

    /** Rows without a date go to a table of their own… */
    public const UNDATED_SEPARATE = 'separate';

    /** …or to the newest of the new tables. */
    public const UNDATED_LATEST = 'latest';

    public const UNDATED = [self::UNDATED_SEPARATE, self::UNDATED_LATEST];

    protected $fillable = ['user_id', 'section', 'period', 'undated', 'source_ids', 'result_ids', 'status', 'summary', 'applied_at', 'undone_at'];

    protected $casts = [
        'section' => DataTableSection::class,
        'period' => TablePeriod::class,
        'source_ids' => 'array',
        'result_ids' => 'array',
        'summary' => 'array',
        'applied_at' => 'immutable_datetime',
        'undone_at' => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The tables that went in, archived ones too. */
    public function sources(): Builder
    {
        return DataTable::withArchived()->whereIn('id', $this->source_ids ?? []);
    }

    /** The tables it made. */
    public function results(): Builder
    {
        return DataTable::withArchived()->whereIn('id', $this->result_ids ?? []);
    }
}
