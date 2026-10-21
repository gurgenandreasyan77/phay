<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One calendar of a connected Google account; the selected ones are synced. */
class GoogleCalendar extends Model
{
    use BelongsToTenant;

    protected $fillable = ['google_connection_id', 'calendar_id', 'name', 'color', 'is_primary', 'selected', 'sync_token', 'synced_at'];

    protected $hidden = ['sync_token'];

    protected $casts = [
        'is_primary' => 'boolean',
        'selected' => 'boolean',
        'synced_at' => 'immutable_datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleConnection::class, 'google_connection_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GoogleEvent::class);
    }
}
