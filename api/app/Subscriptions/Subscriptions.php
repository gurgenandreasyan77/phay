<?php

namespace App\Subscriptions;

use App\Features\FeatureFlag;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\SupportChat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

/**
 * Everything that changes a business's subscription: the trial it starts
 * with, payments and free access the super admin gives, turning it off and
 * back on, and the messages the business gets in its chat with the platform
 * before and when it ends. A business whose subscription ended keeps all its
 * data; paying (or free access) opens the admin again.
 */
class Subscriptions
{
    public const UNITS = ['day', 'week', 'month'];

    public function __construct(private readonly SupportChat $chat) {}

    public function warnDays(): int
    {
        return max(1, (int) config('platform.subscription_warn_days', 10));
    }

    /**
     * The business's subscription, made on first use for a business that
     * has none: the default plan's trial from the day the business was
     * created; Servio itself never pays.
     */
    public function for(Tenant $tenant): Subscription
    {
        if ($tenant->relationLoaded('subscription') && $tenant->subscription) {
            return $tenant->subscription;
        }

        $subscription = Subscription::query()->firstOrCreate(['tenant_id' => $tenant->id], $this->starting($tenant));
        $tenant->setRelation('subscription', $subscription);
        $subscription->setRelation('tenant', $tenant);

        return $subscription;
    }

    /** @return array<string, mixed> */
    private function starting(Tenant $tenant): array
    {
        if ($tenant->slug === config('platform.default_tenant')) {
            return ['plan_id' => Plan::default()?->id, 'unlimited' => true];
        }

        $plan = Plan::default();
        $start = $tenant->created_at ?? now();

        return [
            'plan_id' => $plan?->id,
            'trial_ends_at' => $start->copy()->addDays($plan?->trial_days ?? 0),
        ];
    }

    /**
     * What the business pays a month: its plan's price plus every module it
     * has on that the plan doesn't include.
     *
     * @return array{plan: int, modules: list<array{key: string, label: string, price: int, active: bool, included: bool}>, total: int}
     */
    public function pricing(Tenant $tenant, ?Subscription $subscription = null, ?array $prices = null): array
    {
        $subscription ??= $this->for($tenant);
        $plan = $subscription->plan;
        $prices ??= ModulePrice::map();

        $modules = [];
        $total = $plan?->price ?? 0;

        foreach (FeatureFlag::cases() as $flag) {
            $active = Feature::for($tenant)->active($flag->value);
            $included = (bool) $plan?->includes($flag->value);
            $price = $prices[$flag->value] ?? 0;

            if ($active && ! $included) {
                $total += $price;
            }

            $modules[] = [
                'key' => $flag->value,
                'label' => $flag->label(),
                'price' => $price,
                'active' => $active,
                'included' => $included,
            ];
        }

        return ['plan' => $plan?->price ?? 0, 'modules' => $modules, 'total' => $total];
    }

    /**
     * Sends the business what it hasn't been told yet: that its
     * subscription ends soon, or that it has ended. Once per end date, so
     * calling it on every request is safe; a new end date (a payment, free
     * access) gets its own warning.
     */
    public function sync(Tenant $tenant, ?Subscription $subscription = null): Subscription
    {
        $subscription ??= $this->for($tenant);
        $ends = $subscription->endsAt();

        if ($ends === null) {
            return $subscription;
        }

        if (! $subscription->allowsAccess()) {
            if (! $this->same($ends, $subscription->expired_notified_for)) {
                $this->chat->notify($tenant, $this->expiredMessage(), [
                    'type' => NotificationType::Subscription,
                    'title' => 'Բաժանորդագրությունն ավարտվել է',
                    'url' => '/admin/subscription',
                ]);
                $subscription->forceFill(['expired_notified_for' => $ends, 'warned_for' => $ends])->save();
            }

            return $subscription;
        }

        if ($subscription->isEnding($this->warnDays()) && ! $this->same($ends, $subscription->warned_for)) {
            $this->chat->notify($tenant, $this->endingMessage($tenant, $subscription), [
                'type' => NotificationType::Subscription,
                'title' => sprintf('Բաժանորդագրությունն ավարտվում է %d օր հետո', $subscription->daysLeft()),
                'url' => '/admin/subscription',
            ]);
            $subscription->forceFill(['warned_for' => $ends])->save();
        }

        return $subscription;
    }

    public function changePlan(Subscription $subscription, Plan $plan): Subscription
    {
        $subscription->plan()->associate($plan)->save();

        return $subscription;
    }

    public function setUnlimited(Subscription $subscription, bool $unlimited): Subscription
    {
        $subscription->forceFill(['unlimited' => $unlimited, 'cancel_at' => $unlimited ? null : $subscription->cancel_at])->save();

        return $subscription;
    }

    /**
     * A payment for some months: it extends the subscription from where it
     * would end anyway (or from today, if it already ended) and undoes a
     * pending turn-off. The amount defaults to the monthly price × months.
     */
    public function recordPayment(Subscription $subscription, int $months, ?int $amount, ?string $note, ?User $by): SubscriptionPayment
    {
        $tenant = $subscription->tenant;
        $amount ??= $this->pricing($tenant, $subscription)['total'] * $months;

        return DB::transaction(function () use ($subscription, $tenant, $months, $amount, $note, $by) {
            $from = $this->extendFrom($subscription);
            $to = $from->copy()->addMonthsNoOverflow($months);

            $subscription->forceFill(['paid_until' => $to, 'cancel_at' => null])->save();

            $payment = SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'user_id' => $by?->id,
                'amount' => $amount,
                'months' => $months,
                'period_from' => $from,
                'period_to' => $to,
                'note' => $note,
            ]);

            $this->chat->notify($tenant, sprintf(
                'Շնորհակալություն, վճարումը ստացված է (%s դր)։ Բաժանորդագրությունը գործում է մինչև %s։',
                $this->money($amount),
                $this->date($tenant, $to),
            ));

            return $payment;
        });
    }

    /**
     * Takes back the latest payment (entered by mistake): the paid period
     * goes back to where that payment started it.
     */
    public function deletePayment(Subscription $subscription, SubscriptionPayment $payment): void
    {
        $latest = SubscriptionPayment::query()->where('tenant_id', $subscription->tenant_id)->latest('id')->first();

        if (! $latest || ! $latest->is($payment)) {
            throw new InvalidArgumentException('Only the latest payment can be removed.');
        }

        DB::transaction(function () use ($subscription, $payment) {
            $previous = SubscriptionPayment::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->whereKeyNot($payment->id)
                ->latest('id')
                ->first();

            $subscription->forceFill(['paid_until' => $previous?->period_to])->save();
            $payment->delete();
        });
    }

    /**
     * Free access for some days, weeks or months, added after the current
     * end (so giving another month extends it). Undoes a pending turn-off.
     */
    public function grantFree(Subscription $subscription, int $amount, string $unit, ?User $by = null): Subscription
    {
        if (! in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException("Unknown unit [{$unit}].");
        }

        $from = $this->extendFrom($subscription);
        $until = match ($unit) {
            'day' => $from->copy()->addDays($amount),
            'week' => $from->copy()->addWeeks($amount),
            'month' => $from->copy()->addMonthsNoOverflow($amount),
        };

        $subscription->forceFill(['free_until' => $until, 'cancel_at' => null])->save();

        $this->chat->notify($subscription->tenant, sprintf(
            'Ձեզ տրամադրվել է անվճար մուտք մինչև %s։',
            $this->date($subscription->tenant, $until),
        ));

        return $subscription;
    }

    public function removeFree(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['free_until' => null])->save();

        return $subscription;
    }

    /**
     * Turns the subscription off after a notice of some days (0: right
     * away). The business is told now; its data stays.
     */
    public function cancel(Subscription $subscription, int $days): Subscription
    {
        $at = now()->addDays($days);
        $tenant = $subscription->tenant;

        $subscription->forceFill([
            'cancel_at' => $at,
            'unlimited' => false,
            // This message is the warning for this end date.
            'warned_for' => $at,
        ])->save();

        if ($days > 0) {
            $pricing = $this->pricing($tenant, $subscription);
            $this->chat->notify($tenant, sprintf(
                'Ձեր բաժանորդագրությունը կանջատվի %s-ին (%d օր հետո)։ Որպեսզի շարունակեք օգտվել ադմինից, խնդրում ենք վճարել բաժանորդագրությունը (%s դր/ամիս)։ Ձեր բոլոր տվյալները պահպանվում են։',
                $this->date($tenant, $at),
                $days,
                $this->money($pricing['total']),
            ));
        }

        return $this->sync($tenant, $subscription);
    }

    /** Undoes a turn-off: the subscription runs as it did before. */
    public function resume(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['cancel_at' => null])->save();

        if ($subscription->allowsAccess() && $subscription->expired_notified_for) {
            $this->chat->notify($subscription->tenant, 'Ձեր բաժանորդագրությունը կրկին ակտիվ է։');
        }

        return $subscription;
    }

    /** Where an extension starts: the current end, or now if that has passed. */
    private function extendFrom(Subscription $subscription): Carbon
    {
        $covered = $subscription->coveredUntil();

        return $covered && $covered->isFuture() ? $covered->copy() : now();
    }

    private function same(Carbon $a, ?Carbon $b): bool
    {
        return $b !== null && $a->timestamp === $b->timestamp;
    }

    private function endingMessage(Tenant $tenant, Subscription $subscription): string
    {
        $pricing = $this->pricing($tenant, $subscription);

        return sprintf(
            'Ձեր բաժանորդագրությունն ավարտվում է %s-ին (%d օր հետո)։ Որպեսզի ադմինը շարունակի աշխատել առանց ընդհատման, խնդրում ենք երկարացնել բաժանորդագրությունը (%s դր/ամիս)։ Ձեր բոլոր տվյալները պահպանվում են։',
            $this->date($tenant, $subscription->endsAt()),
            $subscription->daysLeft(),
            $this->money($pricing['total']),
        );
    }

    private function expiredMessage(): string
    {
        return 'Ձեր բաժանորդագրությունն ավարտվել է, և ադմինը ժամանակավորապես անհասանելի է։ Ձեր բոլոր տվյալները պահպանված են. վճարելուց հետո մուտքը կվերականգնվի։ Գրեք մեզ այստեղ՝ վճարման մանրամասների համար։';
    }

    private function date(Tenant $tenant, Carbon $date): string
    {
        return $date->copy()->setTimezone($tenant->timezone ?: config('app.timezone'))->format('d.m.Y');
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }
}
