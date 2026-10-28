<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One row of a business's balance ledger: a top-up the super admin made, a
 * charge for what the business bought (or an automatic renewal), a refund
 * of a payment taken back, or a correction. Only ever added: a mistake is
 * fixed by a new row, so the history always adds up to the balance. Not
 * tenant-scoped (the platform level reads it for any business).
 */
class BalanceTransaction extends Model
{
    public const TOP_UP = 'top_up';

    public const CHARGE = 'charge';

    public const REFUND = 'refund';

    public const CORRECTION = 'correction';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'type',
        'amount',
        'balance_after',
        'purpose',
        'description',
        'note',
        'reference_type',
        'reference_id',
        'meta',
        'automatic',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
        'automatic' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Balance transactions are never changed.'));
        static::deleting(fn () => throw new LogicException('Balance transactions are never deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, mixed> */
    public function present(bool $withAuthor = false): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balanceAfter' => $this->balance_after,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'note' => $this->note,
            'automatic' => $this->automatic,
            // Who on the platform did it: only the platform level sees names.
            ...($withAuthor ? ['by' => $this->user?->name] : []),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
