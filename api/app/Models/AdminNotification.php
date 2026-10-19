<?php

namespace App\Models;

use App\Notifications\NotificationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened for one person, shown under the bell in the admin's
 * header: a new order from the site, a message in the chat with the
 * platform, the subscription ending, today's orders. Not tenant-scoped: a
 * platform-level notification is about a business the reader isn't in.
 */
class AdminNotification extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'scope',
        'type',
        'title',
        'body',
        'url',
        'dedupe_key',
        'read_at',
    ];

    protected $casts = [
        'scope' => NotificationScope::class,
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * What this person sees in the business they are in: its own
     * notifications and, for platform staff, the platform's.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?Tenant $tenant): Builder
    {
        return $query->where('user_id', $user->id)->where(fn (Builder $q) => $q
            ->where('scope', NotificationScope::Platform)
            ->orWhere(fn (Builder $q) => $q->where('scope', NotificationScope::Business)->where('tenant_id', $tenant?->id)));
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
