<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** An account's followers on a day it was synced. */
class SocialAccountDay extends Model
{
    use BelongsToTenant;

    protected $fillable = ['social_account_id', 'date', 'followers', 'media_count'];

    protected $casts = [
        'followers' => 'integer',
        'media_count' => 'integer',
    ];
}
