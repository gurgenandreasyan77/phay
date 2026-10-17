<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A website connected to a tenant. Its public_key travels in the
 * X-Site-Key header and decides whose content the public API serves.
 *
 * The key is publishable (the browser needs it for the lead form), so it
 * is not a secret: allowed_origins, rate limits and the honeypot are what
 * protect it, the same way a Stripe publishable key works.
 */
class Site extends Model
{
    public const KEY_PREFIX = 'site_pk_';

    protected $fillable = [
        'tenant_id',
        'name',
        'domain',
        'public_key',
        'allowed_origins',
        'is_active',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            $site->public_key ??= static::generateKey();
        });
    }

    public static function generateKey(): string
    {
        return self::KEY_PREFIX.Str::random(32);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * An empty list means "any origin" (server-side callers such as the
     * Next.js site send no Origin header at all).
     */
    public function allowsOrigin(?string $origin): bool
    {
        if ($origin === null || empty($this->allowed_origins)) {
            return true;
        }

        return in_array(rtrim($origin, '/'), array_map(fn ($o) => rtrim($o, '/'), $this->allowed_origins), true);
    }
}
