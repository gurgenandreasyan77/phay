<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan of the platform (made by the super admin): its monthly
 * price in AMD, the trial a new business gets, the modules whose price it
 * already includes and the AI assistant requests a month (null: no limit).
 * Platform-wide, so not tenant-scoped.
 */
class Plan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'trial_days',
        'assistant_requests',
        'included_modules',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'trial_days' => 'integer',
        'assistant_requests' => 'integer',
        'included_modules' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** The plan new businesses start on (the first active one if none is marked). */
    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->orderByDesc('is_default')->ordered()->first();
    }

    public function includes(string $featureKey): bool
    {
        return in_array($featureKey, $this->included_modules ?? [], true);
    }
}
