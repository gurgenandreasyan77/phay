<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Two rows of different tables the owner said are different records
 * («keep both»): App\DataTables\Duplicates\RowDuplicates never counts them
 * once nor asks about them again. row_id is the smaller id.
 */
class RowDuplicateDismissal extends Model
{
    use BelongsToTenant;

    protected $fillable = ['row_id', 'other_row_id', 'user_id'];
}
