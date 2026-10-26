<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Instagram account the business watches: only what Instagram shows to
 * everyone (followers, posts and their likes and comments), read through
 * Business Discovery. Private figures of another account are never available.
 */
class SocialCompetitor extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const OK = 'ok';

    public const NOT_FOUND = 'not_found';

    public const ERROR = 'error';

    protected $fillable = ['added_by', 'username', 'name', 'biography', 'website', 'picture_url', 'followers', 'follows', 'media_count', 'stats', 'recent_posts', 'status', 'error', 'synced_at'];

    protected $casts = [
        'followers' => 'integer',
        'follows' => 'integer',
        'media_count' => 'integer',
        'stats' => 'array',
        'recent_posts' => 'array',
        'synced_at' => 'immutable_datetime',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(SocialCompetitorDay::class);
    }

    /** Forgets what was read about the account; the username stays on the list. */
    public function forgetFigures(): void
    {
        $this->days()->delete();
        $this->forceFill([
            'name' => null, 'biography' => null, 'website' => null, 'picture_url' => null,
            'followers' => null, 'follows' => null, 'media_count' => null,
            'stats' => null, 'recent_posts' => null, 'status' => self::PENDING, 'error' => null, 'synced_at' => null,
        ])->save();
    }
}
