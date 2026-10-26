<?php

namespace App\Models;

use App\Agents\Agent;
use App\Agents\AgentCatalog;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI agent (App\Agents\AgentCatalog) of one business: whether the
 * business has it (the super admin turns it on, like a module) and what
 * it was told to remember about the business.
 */
class TenantAgent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['agent_key', 'active', 'activated_at', 'activated_by', 'notes'];

    protected $casts = [
        'active' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function agent(): ?Agent
    {
        return AgentCatalog::find($this->agent_key);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
