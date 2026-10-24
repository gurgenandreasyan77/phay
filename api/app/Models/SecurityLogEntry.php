<?php

namespace App\Models;

use App\Enums\SecurityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the security audit log: sign-ins (and failed ones), 2FA,
 * passwords, invitations, sessions. Written only, never edited.
 */
class SecurityLogEntry extends Model
{
    protected $table = 'security_events';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_id', 'email', 'event', 'ip_address', 'user_agent', 'meta'];

    protected $casts = [
        'event' => SecurityEvent::class,
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
