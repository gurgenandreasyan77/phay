<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A change of the team's records the HR agent prepared (App\Team\HrChanges):
 * planned (waiting for the owner's press), applied, undone or discarded.
 */
class HrChange extends Model
{
    use BelongsToTenant;

    public const PLANNED = 'planned';

    public const APPLIED = 'applied';

    public const UNDONE = 'undone';

    public const DISCARDED = 'discarded';

    protected $fillable = ['user_id', 'title', 'ops', 'result', 'status', 'applied_at', 'undone_at'];

    protected $casts = [
        'ops' => 'array',
        'result' => 'array',
        'applied_at' => 'immutable_datetime',
        'undone_at' => 'immutable_datetime',
    ];
}
