<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An event of a synced Google calendar, as last read: a copy shown in the
 * business's calendar and turned into table rows on request. starts_at and
 * ends_at are the business's local time.
 */
class GoogleEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['google_calendar_id', 'event_id', 'summary', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'html_link', 'google_updated'];

    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'all_day' => 'boolean',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendar::class, 'google_calendar_id');
    }
}
