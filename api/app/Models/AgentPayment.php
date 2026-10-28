<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Months of AI agents a business pays for (App\Agents\AgentTerms): asked
 * for by the owner (requested) until the super admin records the money
 * (paid). Each line moves one agent's end to its `to`. Read by the platform
 * level for any business, so it is not tenant-scoped.
 */
class AgentPayment extends Model
{
    public const REQUESTED = 'requested';

    public const PAID = 'paid';

    protected $fillable = [
        'tenant_id',
        'status',
        'amount',
        'until',
        'months',
        'lines',
        'note',
        'requested_by',
        'recorded_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'months' => 'integer',
        'until' => 'datetime',
        'lines' => 'array',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return list<string> the agents it pays for */
    public function agentKeys(): array
    {
        return array_values(array_map(fn (array $line) => $line['agent'], $this->lines ?? []));
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'until' => $this->until?->toIso8601String(),
            'months' => $this->months,
            'lines' => array_map(fn (array $line) => [
                'agent' => $line['agent'],
                'from' => $line['from'],
                'to' => $line['to'],
                'days' => $line['days'],
                'amount' => $line['amount'],
            ], $this->lines ?? []),
            'note' => $this->note,
            'requestedBy' => $this->requester?->name,
            'recordedBy' => $this->recorder?->name,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
