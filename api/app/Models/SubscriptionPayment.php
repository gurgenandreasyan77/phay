<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment the super admin recorded for a business: how much, for how many
 * months, and the stretch of time it paid for.
 */
class SubscriptionPayment extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'amount',
        'months',
        'period_from',
        'period_to',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'months' => 'integer',
        'period_from' => 'datetime',
        'period_to' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
