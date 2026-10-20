<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A message a visitor sent through the feedback form of the business's
 * site (a question, a review, a complaint: not an order). read_at is set
 * when someone in the business marks it read.
 */
class FeedbackMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'message',
        'locale',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
