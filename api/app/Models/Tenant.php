<?php

namespace App\Models;

use App\Features\FeatureFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Pennant\Contracts\FeatureScopeSerializeable;
use Laravel\Pennant\Feature;
use RuntimeException;

/**
 * A business using the platform. Every content row (services, products,
 * leads, settings...) belongs to exactly one tenant via BelongsToTenant.
 */
class Tenant extends Model implements FeatureScopeSerializeable
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'default_locale',
        'locales',
        'currency',
        'timezone',
        'status',
    ];

    protected $casts = [
        'locales' => 'array',
    ];

    protected static function booted(): void
    {
        // A new business starts with only the basic sections: optional
        // modules (Auto Care, Market...) stay off until the platform
        // administrator turns them on for it in Feature Flags. Servio itself
        // was inserted by a migration and keeps its own flags.
        static::created(function (Tenant $tenant) {
            Feature::for($tenant)->deactivate(array_map(fn (FeatureFlag $flag) => $flag->value, FeatureFlag::cases()));
        });
    }

    public static function default(): self
    {
        $slug = config('platform.default_tenant');

        return static::query()->where('slug', $slug)->first()
            ?? throw new RuntimeException("Default tenant [{$slug}] is missing. Run the migrations.");
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // Stable Pennant scope ("tenant:1") that survives a class rename,
    // unlike the default "App\Models\Tenant|1".
    public function featureScopeSerialize(): string
    {
        return 'tenant:'.$this->getKey();
    }
}
