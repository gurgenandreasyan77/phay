<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Facebook Page or Instagram professional account the business's login reads; the selected ones are synced. */
class SocialAccount extends Model
{
    use BelongsToTenant;

    public const INSTAGRAM = 'instagram';

    public const FACEBOOK = 'facebook';

    protected $fillable = [
        'social_connection_id', 'network', 'external_id', 'page_id', 'username', 'name', 'picture_url', 'page_token',
        'selected', 'followers', 'follows', 'media_count', 'profile', 'insights', 'audience', 'audience_synced_at', 'synced_at',
    ];

    protected $hidden = ['page_token'];

    protected $casts = [
        'page_token' => 'encrypted',
        'selected' => 'boolean',
        'followers' => 'integer',
        'follows' => 'integer',
        'media_count' => 'integer',
        'profile' => 'array',
        'insights' => 'array',
        'audience' => 'array',
        'audience_synced_at' => 'immutable_datetime',
        'synced_at' => 'immutable_datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class, 'social_connection_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(SocialAccountDay::class);
    }

    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('selected', true);
    }

    public function isInstagram(): bool
    {
        return $this->network === self::INSTAGRAM;
    }

    /** @username on Instagram, the Page's name on Facebook. */
    public function handle(): string
    {
        return $this->isInstagram() && $this->username ? '@'.$this->username : (string) ($this->name ?? $this->username ?? $this->external_id);
    }
}
