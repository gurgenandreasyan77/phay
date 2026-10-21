<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's Google account connected to a business, read only. The tokens
 * are stored encrypted and never leave the API.
 */
class GoogleConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'email', 'access_token', 'refresh_token', 'expires_at', 'scopes', 'revoked_at', 'synced_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'synced_at' => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(GoogleCalendar::class);
    }
}
