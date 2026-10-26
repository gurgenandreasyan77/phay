<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A watched account's followers on a day it was read. */
class SocialCompetitorDay extends Model
{
    use BelongsToTenant;

    protected $fillable = ['social_competitor_id', 'date', 'followers', 'media_count'];

    protected $casts = [
        'followers' => 'integer',
        'media_count' => 'integer',
    ];
}
