<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlagLog extends Model
{
    protected $fillable = [
        'feature',
        'old_value',
        'new_value',
        'changed_by_id',
    ];

    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
