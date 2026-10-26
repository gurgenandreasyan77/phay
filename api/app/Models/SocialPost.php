<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A post of a connected account with its figures, as last synced. */
class SocialPost extends Model
{
    use BelongsToTenant;

    protected $fillable = ['social_account_id', 'external_id', 'kind', 'caption', 'permalink', 'posted_at', 'likes', 'comments', 'shares', 'saves', 'reach', 'views', 'interactions'];

    protected $casts = [
        'posted_at' => 'immutable_datetime',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saves' => 'integer',
        'reach' => 'integer',
        'views' => 'integer',
        'interactions' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
