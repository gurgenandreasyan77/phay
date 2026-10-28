<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The money a business keeps with the platform, in dram (App\Billing\Balance).
 * Changed only through the ledger (BalanceTransaction). Read by the platform
 * level for any business, so it is not tenant-scoped.
 */
class BusinessBalance extends Model
{
    protected $fillable = ['tenant_id', 'amount', 'auto_renew'];

    protected $casts = [
        'amount' => 'integer',
        'auto_renew' => 'boolean',
        'short_notified_for' => 'datetime',
    ];

    protected $attributes = [
        'amount' => 0,
        'auto_renew' => true,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
