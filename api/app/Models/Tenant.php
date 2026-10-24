<?php

namespace App\Models;

use App\Features\FeatureFlag;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'logo',
        'slug',
        'default_locale',
        'locales',
        'currency',
        'timezone',
        'status',
    ];

    protected $casts = [
        'locales' => 'array',
        'require_two_factor' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A new business starts with the basic sections (orders and sales
        // included): the modules (Services, Market, Site) stay off until the
        // platform administrator turns them on for it. The default business
        // was inserted by a migration and keeps its own flags.
        static::created(function (Tenant $tenant) {
            foreach (FeatureFlag::cases() as $flag) {
                $flag->onForNewBusiness()
                    ? Feature::for($tenant)->activate($flag->value)
                    : Feature::for($tenant)->deactivate($flag->value);
            }
        });
    }

    /**
     * The business named by config('platform.default_tenant'), else the
     * oldest one: the tenancy migration creates it before any other.
     */
    public static function default(): self
    {
        $slug = config('platform.default_tenant');

        return static::query()->where('slug', $slug)->first()
            ?? static::query()->oldest('id')->first()
            ?? throw new RuntimeException("Default tenant [{$slug}] is missing. Run the migrations.");
    }

    public function isDefault(): bool
    {
        return $this->is(static::default());
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    // The relations below read another business's rows from the platform
    // level, so they skip the current-tenant scope (the foreign key already
    // limits them to this business).

    public function contactChannels(): HasMany
    {
        return $this->hasMany(ContactChannel::class)->withoutGlobalScope(TenantScope::class);
    }

    public function siteSetting(): HasOne
    {
        return $this->hasOne(SiteSetting::class)->withoutGlobalScope(TenantScope::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->withoutGlobalScope(TenantScope::class);
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
