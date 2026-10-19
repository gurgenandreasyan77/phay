<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A business's subscription. Access lasts until the latest of the trial, the
 * paid period and the free access the super admin gave; an unlimited one
 * never ends, and one the super admin turned off ends at cancel_at. Read by
 * the platform level for any business, so it is not tenant-scoped.
 */
class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'trial_ends_at',
        'paid_until',
        'free_until',
        'unlimited',
        'cancel_at',
        'warned_for',
        'expired_notified_for',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'paid_until' => 'datetime',
        'free_until' => 'datetime',
        'unlimited' => 'boolean',
        'cancel_at' => 'datetime',
        'warned_for' => 'datetime',
        'expired_notified_for' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Until when the trial, payments and free access reach, ignoring cancel_at. */
    public function coveredUntil(): ?Carbon
    {
        $dates = array_filter([$this->trial_ends_at, $this->paid_until, $this->free_until]);

        return $dates === [] ? null : collect($dates)->max();
    }

    /** When access ends; null for an unlimited one. */
    public function endsAt(): ?Carbon
    {
        if ($this->unlimited && ! $this->cancel_at) {
            return null;
        }

        return $this->cancel_at ?? $this->coveredUntil() ?? $this->created_at;
    }

    public function status(?CarbonImmutable $now = null): SubscriptionStatus
    {
        $now ??= CarbonImmutable::now();
        $ends = $this->endsAt();

        if ($ends === null) {
            return SubscriptionStatus::Unlimited;
        }
        if ($ends->lte($now)) {
            return SubscriptionStatus::Expired;
        }
        if ($this->cancel_at) {
            return SubscriptionStatus::Canceling;
        }
        if ($this->paid_until?->gt($now)) {
            return SubscriptionStatus::Active;
        }
        if ($this->free_until?->gt($now)) {
            return SubscriptionStatus::Free;
        }

        return SubscriptionStatus::Trial;
    }

    public function allowsAccess(): bool
    {
        return $this->status()->allowsAccess();
    }

    /** Whole days left (rounded up: ending later today is 1); null when it never ends. */
    public function daysLeft(): ?int
    {
        $ends = $this->endsAt();

        if ($ends === null) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($ends, false) / 86400));
    }

    /** Ends within the warning window (and hasn't ended yet). */
    public function isEnding(int $warnDays): bool
    {
        $left = $this->daysLeft();

        return $left !== null && $this->allowsAccess() && $left <= $warnDays;
    }
}
