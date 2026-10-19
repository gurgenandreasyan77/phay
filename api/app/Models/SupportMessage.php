<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message between a business and the platform team (one conversation per
 * business). from_platform says which side wrote it; read_at is set when the
 * other side opens the conversation.
 */
class SupportMessage extends Model
{
    use BelongsToTenant;

    /** How many of the latest messages a conversation shows. */
    public const WINDOW = 200;

    protected $fillable = [
        'user_id',
        'from_platform',
        'body',
        'read_at',
    ];

    protected $casts = [
        'from_platform' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Messages written by the given side that the other side hasn't opened yet. */
    public function scopeUnreadFrom(Builder $query, bool $fromPlatform): Builder
    {
        return $query->where('from_platform', $fromPlatform)->whereNull('read_at');
    }
}
