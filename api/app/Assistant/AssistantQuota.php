<?php

namespace App\Assistant;

use App\Models\AssistantBonus;
use App\Models\AssistantUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Subscriptions\Subscriptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * How many questions a business may still ask the AI assistant.
 *
 * Each plan gives a number of requests a month (the super admin sets it;
 * null: no limit). The month is the business's subscription month: it
 * starts on the day the business started paying (or, before any payment,
 * the day its trial started) and renews on that day of every month, so a
 * business that paid on the 17th gets new requests on the 17th. Bonus
 * requests the super admin gives by hand don't expire: they are spent once
 * the plan's requests of a month run out. An unlimited subscription
 * (the default business) has no limit.
 *
 * Usage rows are always looked up by tenant_id, so the platform level can
 * read any business's quota.
 */
class AssistantQuota
{
    public function __construct(private readonly Subscriptions $subscriptions) {}

    /**
     * Where the business stands this subscription month.
     *
     * @return array{used: int, limit: int|null, planLimit: int|null, bonus: int, remaining: int|null, unlimited: bool, periodStart: string, resetsAt: string}
     */
    public function status(Tenant $tenant): array
    {
        $subscription = $this->subscriptions->for($tenant);
        [$start, $end] = $this->cycle($subscription);
        $planLimit = $this->planLimit($subscription);
        $usage = $this->usage($tenant, $start);
        $bonus = $this->bonusBalance($tenant);

        $used = $usage?->requests ?? 0;
        $planUsed = $used - ($usage?->bonus_used ?? 0);
        $remaining = $planLimit === null ? null : max(0, $planLimit - $planUsed) + $bonus;

        return [
            'used' => $used,
            // What this month allows in all: the plan's requests plus the
            // bonus ones (spent or not) — "used of limit" on the screen.
            'limit' => $planLimit === null ? null : $planLimit + ($usage?->bonus_used ?? 0) + $bonus,
            'planLimit' => $planLimit,
            'bonus' => $bonus,
            'remaining' => $remaining,
            'unlimited' => $planLimit === null,
            'periodStart' => $start->toIso8601String(),
            'resetsAt' => $end->toIso8601String(),
        ];
    }

    public function exhausted(Tenant $tenant): bool
    {
        $remaining = $this->status($tenant)['remaining'];

        return $remaining !== null && $remaining <= 0;
    }

    /**
     * Counts one answered question and the tokens it cost. Past the plan's
     * requests of the month, it is paid with a bonus request.
     */
    public function record(Tenant $tenant, int $inputTokens, int $outputTokens): void
    {
        $subscription = $this->subscriptions->for($tenant);
        [$start] = $this->cycle($subscription);
        $planLimit = $this->planLimit($subscription);

        DB::transaction(function () use ($tenant, $start, $planLimit, $inputTokens, $outputTokens) {
            // Two answers at once may both start the month's row: the second one is skipped, not an error.
            AssistantUsage::withoutTenancy()->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'period_start' => $start->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $usage = $this->usage($tenant, $start, lock: true);

            $fromBonus = $planLimit !== null
                && $usage->requests - $usage->bonus_used >= $planLimit
                && $this->bonusBalance($tenant) > 0;

            AssistantUsage::withoutTenancy()->whereKey($usage->id)->update([
                'requests' => DB::raw('requests + 1'),
                'bonus_used' => DB::raw('bonus_used + '.($fromBonus ? 1 : 0)),
                'input_tokens' => DB::raw('input_tokens + '.max(0, $inputTokens)),
                'output_tokens' => DB::raw('output_tokens + '.max(0, $outputTokens)),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Gives the business extra requests (a negative amount takes back what
     * is left of them, never below zero).
     */
    public function grantBonus(Tenant $tenant, int $amount, ?string $note, ?User $by): AssistantBonus
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('The amount must not be zero.');
        }

        $amount = max($amount, -$this->bonusBalance($tenant));

        return AssistantBonus::create([
            'tenant_id' => $tenant->id,
            'user_id' => $by?->id,
            'amount' => $amount,
            'note' => $note,
        ]);
    }

    /** Bonus requests given and not spent yet. */
    public function bonusBalance(Tenant $tenant): int
    {
        $given = (int) AssistantBonus::query()->where('tenant_id', $tenant->id)->sum('amount');
        $spent = (int) AssistantUsage::withoutTenancy()->where('tenant_id', $tenant->id)->sum('bonus_used');

        return max(0, $given - $spent);
    }

    /**
     * Plans that give more requests a month than the business's own, the
     * cheapest first: what to offer when its requests run out.
     *
     * @return list<array{id: int, name: string, price: int, assistantRequests: int|null}>
     */
    public function biggerPlans(Tenant $tenant): array
    {
        $subscription = $this->subscriptions->for($tenant);
        $current = $this->planLimit($subscription);

        if ($current === null) {
            return [];
        }

        return Plan::query()
            ->where('is_active', true)
            ->whereKeyNot($subscription->plan_id ?? 0)
            ->where(fn ($q) => $q->whereNull('assistant_requests')->orWhere('assistant_requests', '>', $current))
            ->orderBy('price')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'assistantRequests' => $plan->assistant_requests,
            ])
            ->values()
            ->all();
    }

    /**
     * The subscription month that today falls in: [start, end).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cycle(Subscription $subscription, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $anchor = $this->anchor($subscription, $now);

        $months = max(0, (int) floor($anchor->diffInMonths($now)));
        $start = $anchor->addMonthsNoOverflow($months);
        // diffInMonths may round over a short month: step to the right one.
        while ($start->gt($now) && $months > 0) {
            $start = $anchor->addMonthsNoOverflow(--$months);
        }
        while ($anchor->addMonthsNoOverflow($months + 1)->lte($now)) {
            $start = $anchor->addMonthsNoOverflow(++$months);
        }

        return [$start, $anchor->addMonthsNoOverflow($months + 1)];
    }

    /**
     * The day the business's subscription months count from: the start of
     * the payments that run on without a break up to today (paying again
     * before the end keeps the same day; coming back after a break starts
     * from the new payment), else the start of the trial.
     */
    private function anchor(Subscription $subscription, CarbonImmutable $now): CarbonImmutable
    {
        $payments = SubscriptionPayment::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('period_from', '<=', $now)
            ->orderByDesc('period_from')
            ->orderByDesc('id')
            ->get(['period_from', 'period_to']);

        $start = null;
        foreach ($payments as $payment) {
            $from = CarbonImmutable::instance($payment->period_from);
            if ($start === null) {
                // The latest payment counts only while its period runs.
                if (CarbonImmutable::instance($payment->period_to)->lte($now)) {
                    break;
                }
                $start = $from;

                continue;
            }
            if (CarbonImmutable::instance($payment->period_to)->addMinute()->lt($start)) {
                break;
            }
            $start = $from;
        }

        if ($start !== null) {
            return $start;
        }

        $tenant = $subscription->tenant;
        $trialStart = $tenant?->created_at ?? $subscription->created_at ?? $now;

        return CarbonImmutable::instance($trialStart)->min($now);
    }

    private function planLimit(Subscription $subscription): ?int
    {
        if ($subscription->unlimited && ! $subscription->cancel_at) {
            return null;
        }

        $plan = $subscription->plan;

        // A business with no plan at all gets the platform default.
        return $plan ? $plan->assistant_requests : (int) config('services.anthropic.monthly_limit');
    }

    private function usage(Tenant $tenant, CarbonImmutable $start, bool $lock = false): ?AssistantUsage
    {
        return AssistantUsage::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->whereDate('period_start', $start->toDateString())
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();
    }
}
