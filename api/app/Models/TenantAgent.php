<?php

namespace App\Models;

use App\Agents\Agent;
use App\Agents\AgentCatalog;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An AI agent (App\Agents\AgentCatalog) of one business: whether the
 * business has it on its team, until when (its own trial and paid months,
 * App\Agents\AgentTerms) and what it was told to remember about the business.
 */
class TenantAgent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['agent_key', 'active', 'activated_at', 'activated_by', 'notes', 'trial_ends_at', 'paid_until'];

    protected $casts = [
        'active' => 'boolean',
        'activated_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'paid_until' => 'datetime',
        'warned_for' => 'datetime',
        'expired_notified_for' => 'datetime',
    ];

    /** Until when its trial and paid months reach (null: it never had either). */
    public function coveredUntil(): ?Carbon
    {
        $dates = array_filter([$this->trial_ends_at, $this->paid_until]);

        return $dates === [] ? null : collect($dates)->max();
    }

    public function agent(): ?Agent
    {
        return AgentCatalog::find($this->agent_key);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
