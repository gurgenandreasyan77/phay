<?php

namespace App\Models;

use App\Sites\SiteOrigins;
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
        'revalidate_url',
        'revalidate_secret',
        'is_active',
    ];

    protected $hidden = [
        'revalidate_secret',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'revalidate_secret' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            $site->public_key ??= static::generateKey();
        });

        static::saved(fn () => SiteOrigins::forget());
        static::deleted(fn () => SiteOrigins::forget());
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

    /**
     * Origins a browser on this site sends: the listed ones, or the domain
     * with and without www when none are listed.
     *
     * @return list<string>
     */
    public function browserOrigins(): array
    {
        if (! empty($this->allowed_origins)) {
            return array_values(array_map(fn ($o) => rtrim($o, '/'), $this->allowed_origins));
        }

        if (! $this->domain) {
            return [];
        }

        $host = preg_replace('/^www\./i', '', $this->domain);

        return ["https://{$host}", "https://www.{$host}"];
    }

    /** The cache-refresh webhook is set up and can be called. */
    public function canRevalidate(): bool
    {
        return $this->is_active && $this->revalidate_url && $this->revalidate_secret;
    }
}
