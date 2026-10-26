<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Meta (Facebook) login a business connected, read only: one per
 * business. The token is stored encrypted and never leaves the API.
 */
class SocialConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'meta_user_id', 'meta_user_name', 'access_token', 'expires_at', 'scopes', 'revoked_at', 'expiry_warned_at', 'synced_at', 'sync_error'];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'expiry_warned_at' => 'immutable_datetime',
        'synced_at' => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return list<string> */
    public function grantedScopes(): array
    {
        return array_values(array_filter(explode(',', (string) $this->scopes)));
    }
}
