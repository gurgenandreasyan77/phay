<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extra AI assistant requests the super admin gave a business (negative:
 * taken back). Written from the platform level, so not tenant-scoped.
 */
class AssistantBonus extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'amount', 'note'];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
