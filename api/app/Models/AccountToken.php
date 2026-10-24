<?php

namespace App\Models;

use App\Enums\AccountTokenPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time link (invitation, password reset). Only the sha256 of the token
 * is stored, so a leaked database can't be turned into working links.
 */
class AccountToken extends Model
{
    protected $fillable = ['user_id', 'purpose', 'token_hash', 'expires_at', 'used_at', 'created_by_id'];

    protected $casts = [
        'purpose' => AccountTokenPurpose::class,
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
