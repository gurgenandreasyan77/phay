<?php

namespace App\Billing;

use App\Agents\AgentTerms;
use App\Enums\SubscriptionStatus;
use App\Features\FeatureFlag;
use App\Models\AgentPayment;
use App\Models\BalanceTransaction;
use App\Models\FeatureFlagLog;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\SupportChat;
use App\Sites\SiteCacheTag;
use App\Sites\SiteRevalidator;
use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

/**
 * What a business pays for from its balance (App\Billing\Balance): months of
 * its subscription, a bigger plan, a module, AI agents; and what the
 * platform renews from it on its own when a term ends, so the owner doesn't
 * write to the chat for every month.
 *
 * Each purchase and its charge happen in one transaction: when the balance
 * doesn't cover it nothing changes and the answer is 422 (`balance`).
 */
class BalancePayments
{
    /** A term ending within these hours is renewed now (the scheduler runs once a day). */
    public const RENEW_AHEAD_HOURS = 24;

    public function __construct(
        private readonly Balance $balance,
        private readonly Subscriptions $subscriptions,
        private readonly AgentTerms $terms,
        private readonly SupportChat $chat,
        private readonly TenantContext $context,
    ) {}

    /** Paid agents that ended longer ago than this aren't brought back on their own. */
    public function agentGraceDays(): int
    {
        return max(0, (int) config('platform.balance_agent_grace_days', 7));
    }

    /**
     * Months of the subscription at its monthly price (plan + modules),
     * from where it would end anyway (or today, if it ended).
     */
    public function renewSubscription(Tenant $tenant, int $months, ?User $by = null, bool $automatic = false): SubscriptionPayment
    {
        $subscription = $this->subscriptions->for($tenant);
        $subscription->setRelation('tenant', $tenant);

        if ($subscription->unlimited) {
            throw ValidationException::withMessages(['subscription' => ['Ձեր բաժանորդագրությունն անժամկետ է. վճարել պետք չէ։']]);
        }

        $monthly = $this->subscriptions->pricing($tenant, $subscription)['total'];
        if ($monthly <= 0) {
            throw ValidationException::withMessages(['subscription' => ['Ձեր փաթեթն անվճար է. վճարել պետք չէ։']]);
        }

        $amount = $monthly * $months;

        $payment = DB::transaction(function () use ($tenant, $subscription, $months, $amount, $by, $automatic) {
            $payment = $this->subscriptions->recordPayment(
                $subscription,
                $months,
                $amount,
                $automatic ? 'Ինքնաշխատ երկարացում հաշվեկշռից' : 'Հաշվեկշռից',
                $by,
                notify: false,
            );

            $this->balance->charge(
                $tenant,
                $amount,
                'subscription',
                sprintf('Բաժանորդագրություն · %d ամիս (%s – %s)', $months, $this->date($tenant, $payment->period_from), $this->date($tenant, $payment->period_to)),
                $payment,
                $by,
                $automatic,
                ['months' => $months],
            );

            return $payment;
        });

        $this->chat->notify($tenant, sprintf(
            $automatic
                ? 'Բաժանորդագրությունն ինքնաշխատ երկարացվեց մինչև %s։ Հաշվեկշռից գանձվեց %s դր, մնացորդը՝ %s դր։'
                : 'Բաժանորդագրությունը երկարացվեց մինչև %s։ Հաշվեկշռից գանձվեց %s դր, մնացորդը՝ %s դր։',
            $this->date($tenant, $payment->period_to),
            Balance::money($amount),
            Balance::money($this->balance->amount($tenant)),
        ), [
            'type' => NotificationType::Subscription,
            'title' => $automatic ? 'Բաժանորդագրությունն ինքնաշխատ երկարացվեց' : 'Բաժանորդագրությունը երկարացվեց',
            'url' => '/admin/subscription',
        ]);

        return $payment;
    }

    /**
     * Moves the business to a bigger plan. While a paid month runs it pays
     * the difference of the monthly price for the days left of it; after
     * the subscription ended, the first month of the new plan; in a trial
     * or free access nothing until that ends. A smaller plan stays a
     * request in the chat (nothing to give back for a paid month).
     *
     * @return int what was charged
     */
    public function upgradePlan(Tenant $tenant, Plan $plan, ?User $by = null): int
    {
        $subscription = $this->subscriptions->for($tenant);
        $subscription->setRelation('tenant', $tenant);
        $current = $subscription->plan;

        if (! $plan->is_active || $current?->is($plan)) {
            throw ValidationException::withMessages(['plan_id' => ['Ընտրեք այլ փաթեթ։']]);
        }
        if ($plan->price <= ($current?->price ?? 0)) {
            throw ValidationException::withMessages(['plan_id' => ['Ավելի փոքր փաթեթին անցնելու համար գրեք մեզ չատում։']]);
        }

        $status = $subscription->status();
        if (in_array($status, [SubscriptionStatus::Unlimited, SubscriptionStatus::Canceling], true)) {
            throw ValidationException::withMessages(['plan_id' => ['Փաթեթը փոխելու համար գրեք մեզ չատում։']]);
        }

        $old = $this->subscriptions->pricing($tenant, $subscription)['total'];
        $preview = $subscription->replicate()->setRelation('plan', $plan);
        $new = $this->subscriptions->pricing($tenant, $preview)['total'];

        $charged = DB::transaction(function () use ($tenant, $subscription, $plan, $current, $status, $old, $new, $by) {
            $this->subscriptions->changePlan($subscription, $plan);

            if ($status === SubscriptionStatus::Expired) {
                return $this->renewSubscription($tenant, 1, $by)->amount;
            }

            if ($status !== SubscriptionStatus::Active) {
                return 0;
            }

            $amount = $this->part(max(0, $new - $old), $tenant, now(), $subscription->paid_until);
            if ($amount > 0) {
                $this->balance->charge(
                    $tenant,
                    $amount,
                    'plan',
                    sprintf('«%s» → «%s» փաթեթ · տարբերությունը մինչև %s', $current?->name ?? '—', $plan->name, $this->date($tenant, $subscription->paid_until)),
                    null,
                    $by,
                    meta: ['from' => $current?->id, 'to' => $plan->id],
                );
            }

            return $amount;
        });

        if ($status !== SubscriptionStatus::Expired) {
            $this->chat->notify($tenant, sprintf(
                'Դուք անցաք «%s» փաթեթին։ %sՆոր ամսական վճարը՝ %s դր։',
                $plan->name,
                $charged > 0 ? sprintf('Հաշվեկշռից գանձվեց %s դր՝ տարբերությունը մինչև ընթացիկ ամսվա ավարտը։ ', Balance::money($charged)) : '',
                Balance::money($new),
            ), [
                'type' => NotificationType::Subscription,
                'title' => sprintf('Փաթեթը՝ «%s»', $plan->name),
                'url' => '/admin/subscription',
            ]);
        }

        return $charged;
    }

    /**
     * Turns a module on. Inside the plan, or in a trial or free access, it
     * costs nothing now; while a paid month runs, its price for the days
     * left of it. From then on it is part of the monthly price.
     *
     * @return int what was charged
     */
    public function enableModule(Tenant $tenant, FeatureFlag $flag, ?User $by = null): int
    {
        $subscription = $this->subscriptions->for($tenant);
        $subscription->setRelation('tenant', $tenant);

        if (Feature::for($tenant)->active($flag->value)) {
            throw ValidationException::withMessages(['module' => ['Մոդուլն արդեն միացված է։']]);
        }

        $status = $subscription->status();
        if (in_array($status, [SubscriptionStatus::Expired, SubscriptionStatus::Canceling], true)) {
            throw ValidationException::withMessages(['module' => ['Նախ երկարացրեք բաժանորդագրությունը։']]);
        }

        $price = (int) (ModulePrice::map()[$flag->value] ?? 0);
        $included = $subscription->unlimited || (bool) $subscription->plan?->includes($flag->value);
        $amount = ! $included && $status === SubscriptionStatus::Active ? $this->part($price, $tenant, now(), $subscription->paid_until) : 0;

        DB::transaction(function () use ($tenant, $flag, $amount, $subscription, $by) {
            if ($amount > 0) {
                $this->balance->charge(
                    $tenant,
                    $amount,
                    'module',
                    sprintf('«%s» մոդուլ · մինչև %s', $flag->label(), $this->date($tenant, $subscription->paid_until)),
                    null,
                    $by,
                    meta: ['module' => $flag->value],
                );
            }

            $this->context->run($tenant, function () use ($flag, $by) {
                Feature::activate($flag->value);
                FeatureFlagLog::create([
                    'feature' => $flag->value,
                    'old_value' => false,
                    'new_value' => true,
                    'changed_by_id' => $by?->id,
                ]);
            });
        });

        // A module turned on changes what every public endpoint answers.
        app(SiteRevalidator::class)->touch($tenant->id, ...SiteCacheTag::cases());

        $this->chat->notify($tenant, sprintf(
            '«%s» մոդուլը միացված է։ %s',
            $flag->label(),
            $amount > 0
                ? sprintf('Հաշվեկշռից գանձվեց %s դր՝ մինչև ընթացիկ ամսվա ավարտը. հետո մոդուլի գինը մաս է կազմում ամսական վճարի։', Balance::money($amount))
                : 'Այն ներառված է ամսական վճարի մեջ։',
        ), [
            'type' => NotificationType::Subscription,
            'title' => sprintf('«%s» մոդուլը միացված է', $flag->label()),
            'url' => '/admin/subscription#modules',
        ]);

        return $amount;
    }

    /**
     * AI agents for some months or to one common day, as quoted by
     * AgentTerms, paid from the balance at once. An open request to pay
     * (in the chat) is no longer needed.
     *
     * @param  list<string>  $keys
     */
    public function buyAgents(Tenant $tenant, array $keys, ?Carbon $until, int $months, ?User $by = null, bool $automatic = false): AgentPayment
    {
        $quote = $this->terms->quote($tenant, $keys, $until, $months);
        $this->balance->ensureCovers($tenant, $quote['total']);

        $payment = DB::transaction(function () use ($tenant, $keys, $until, $months, $by, $automatic, $quote) {
            if (! $automatic) {
                AgentPayment::query()->where('tenant_id', $tenant->id)->where('status', AgentPayment::REQUESTED)->delete();
            }

            $payment = $this->terms->record(
                $tenant,
                $keys,
                $until,
                $months,
                $quote['total'],
                $automatic ? 'Ինքնաշխատ երկարացում հաշվեկշռից' : 'Հաշվեկշռից',
                $by,
                notify: false,
                quote: $quote,
            );

            if ($quote['total'] > 0) {
                $this->balance->charge(
                    $tenant,
                    $quote['total'],
                    'agents',
                    'AI աշխատակիցներ · '.implode(', ', array_map(fn (array $line) => sprintf('%s մինչև %s', $line['label'], $this->date($tenant, Carbon::parse($line['to']))), $quote['lines'])),
                    $payment,
                    $by,
                    $automatic,
                    ['agents' => array_column($quote['lines'], 'agent')],
                );
            }

            return $payment;
        });

        $this->chat->notify($tenant, sprintf(
            '%s %s։ Հաշվեկշռից գանձվեց %s դր, մնացորդը՝ %s դր։',
            $automatic ? 'AI աշխատակիցներն ինքնաշխատ երկարացվեցին՝' : 'AI աշխատակիցների ժամկետը երկարացվեց՝',
            implode('; ', array_map(fn (array $line) => sprintf('%s՝ մինչև %s', $line['label'], $this->date($tenant, Carbon::parse($line['to']))), $quote['lines'])),
            Balance::money($quote['total']),
            Balance::money($this->balance->amount($tenant)),
        ), [
            'type' => NotificationType::Agent,
            'title' => $automatic ? 'AI աշխատակիցներն ինքնաշխատ երկարացվեցին' : 'AI աշխատակիցների ժամկետը երկարացվեց',
            'url' => '/admin/subscription#agents',
        ]);

        return $payment;
    }

    /**
     * After a top-up: the owner's open request to pay for agents is paid
     * when the balance covers it, then whatever ended (or ends today) is
     * renewed.
     */
    public function afterTopUp(Tenant $tenant, ?User $by): void
    {
        $request = AgentPayment::query()->where('tenant_id', $tenant->id)->where('status', AgentPayment::REQUESTED)->latest('id')->first();

        if ($request) {
            try {
                $this->buyAgents($tenant, $request->agentKeys(), $request->until, $request->months, $request->requester ?? $by);
            } catch (ValidationException) {
                // Not enough yet, or the request no longer fits: it stays for the super admin.
            }
        }

        $this->autoRenew($tenant);
    }

    /**
     * Renews from the balance what ends within a day (or has ended): the
     * subscription for a month at its monthly price, and every paid AI agent
     * for a month from its own end (one that ended more than the grace days
     * ago stays off; a trial is never turned into a payment on its own).
     * Cheap when there is nothing to do, so it runs on every admin request,
     * daily and after a top-up. When the balance doesn't cover the
     * subscription the business is told once per end date.
     *
     * @return list<string> what was renewed: "subscription", "agent:<key>"
     */
    public function autoRenew(Tenant $tenant): array
    {
        $balance = $this->balance->for($tenant);
        if (! $balance->auto_renew || $balance->amount <= 0) {
            return [];
        }

        // Two requests at once renew once: the other one skips.
        return Cache::lock('balance-renew:'.$tenant->id, 30)->get(fn () => $this->renewDue($tenant)) ?: [];
    }

    /** @return list<string> */
    private function renewDue(Tenant $tenant): array
    {
        $renewed = [];
        $soon = now()->addHours(self::RENEW_AHEAD_HOURS);

        $subscription = $this->subscriptions->for($tenant)->refresh();
        $subscription->setRelation('tenant', $tenant);
        $ends = $subscription->endsAt();

        if (! $subscription->unlimited && ! $subscription->cancel_at && $ends && $ends->lte($soon)) {
            $monthly = $this->subscriptions->pricing($tenant, $subscription)['total'];

            if ($monthly > 0) {
                try {
                    $this->renewSubscription($tenant, 1, null, automatic: true);
                    $renewed[] = 'subscription';
                } catch (ValidationException) {
                    $this->tellShort($tenant, $ends, $monthly);
                }
            }
        }

        $subscription = $subscription->refresh()->setRelation('tenant', $tenant);
        $rows = TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->where('active', true)->whereNotNull('paid_until')->get();

        foreach ($rows as $row) {
            $agent = $row->agent();
            $covered = $row->coveredUntil();

            if (! $agent || ! $covered || $this->terms->included($subscription, $agent)) {
                continue;
            }
            if ($covered->gt($soon) || $covered->lt(now()->subDays($this->agentGraceDays()))) {
                continue;
            }

            try {
                $this->buyAgents($tenant, [$agent->key()], null, 1, null, automatic: true);
                $renewed[] = 'agent:'.$agent->key();
            } catch (ValidationException) {
                // Not enough for this one: AgentTerms warns and ends it as without a balance.
            }
        }

        return $renewed;
    }

    /**
     * The business's balance for its home and subscription page: what it
     * holds, the payments coming (the subscription and each paid agent at
     * its end) and how far the balance carries them, and the ledger.
     *
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, bool $platform = false, int $limit = 20): array
    {
        $balance = $this->balance->for($tenant);
        $subscription = $this->subscriptions->for($tenant);
        $subscription->setRelation('tenant', $tenant);
        $pricing = $this->subscriptions->pricing($tenant, $subscription);
        $upcoming = [];

        $ends = $subscription->endsAt();
        if (! $subscription->unlimited && $ends && $pricing['total'] > 0) {
            $upcoming[] = [
                'kind' => 'subscription',
                'key' => 'subscription',
                'label' => 'Բաժանորդագրություն',
                'date' => $ends->toIso8601String(),
                'amount' => $pricing['total'],
                // Only a month the platform can take on its own.
                'auto' => ! $subscription->cancel_at,
            ];
        }

        $prices = ModulePrice::map();
        $rows = TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->where('active', true)->get();
        foreach ($rows as $row) {
            $agent = $row->agent();
            $covered = $row->coveredUntil();
            if (! $agent || ! $covered || $this->terms->included($subscription, $agent)) {
                continue;
            }
            if ($row->paid_until === null && ! $covered->isFuture()) {
                continue;
            }

            $upcoming[] = [
                'kind' => 'agent',
                'key' => $agent->key(),
                'label' => $agent->persona()['hy'],
                'date' => $covered->toIso8601String(),
                'amount' => $this->terms->price($agent, $prices),
                // A trial is paid for by hand, never on its own.
                'auto' => $row->paid_until !== null,
            ];
        }

        usort($upcoming, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        // How far the balance carries what renews on its own, one after another.
        $left = $balance->amount;
        $coversUntil = null;
        foreach ($upcoming as $i => $item) {
            $covered = $item['auto'] && $balance->auto_renew && $left >= $item['amount'];
            if ($covered) {
                $left -= $item['amount'];
                $coversUntil = $item['date'];
            }
            $upcoming[$i]['covered'] = $covered;
        }

        $monthly = $pricing['total'] * ($subscription->unlimited ? 0 : 1)
            + array_sum(array_map(fn (array $item) => $item['kind'] === 'agent' && $item['auto'] ? $item['amount'] : 0, $upcoming));

        $transactions = BalanceTransaction::query()
            ->when($platform, fn ($query) => $query->with('user:id,name'))
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        return [
            'amount' => $balance->amount,
            'autoRenew' => $balance->auto_renew,
            'monthly' => $monthly,
            // Whole months of everything paid (subscription + paid agents) the balance holds.
            'months' => $monthly > 0 ? intdiv($balance->amount, $monthly) : null,
            'upcoming' => $upcoming,
            'coversUntil' => $coversUntil,
            'toppedUp' => (int) BalanceTransaction::query()->where('tenant_id', $tenant->id)->where('type', BalanceTransaction::TOP_UP)->sum('amount'),
            'spent' => -(int) BalanceTransaction::query()->where('tenant_id', $tenant->id)->where('type', BalanceTransaction::CHARGE)->sum('amount'),
            'transactions' => $transactions->map(fn (BalanceTransaction $t) => $t->present($platform))->all(),
        ];
    }

    /** What a monthly price costs for a stretch: by whole months and the rest by the day. */
    private function part(int $monthly, Tenant $tenant, Carbon $from, ?Carbon $to): int
    {
        if ($to === null || $monthly <= 0) {
            return 0;
        }

        $zone = $tenant->timezone ?: config('app.timezone');

        return AgentTerms::amount($monthly, $from->copy()->setTimezone($zone)->startOfDay(), $to->copy()->setTimezone($zone)->startOfDay());
    }

    private function tellShort(Tenant $tenant, Carbon $ends, int $monthly): void
    {
        $balance = $this->balance->for($tenant);
        if ($balance->short_notified_for && $balance->short_notified_for->timestamp === $ends->timestamp) {
            return;
        }

        $this->chat->notify($tenant, sprintf(
            'Հաշվեկշիռը բավարար չէ բաժանորդագրությունն ինքնաշխատ երկարացնելու համար. անհրաժեշտ է %s դր, հաշվեկշռում՝ %s դր։ Համալրելու համար ուղարկեք վճարման անդորրագիրը այստեղ։',
            Balance::money($monthly),
            Balance::money($balance->amount),
        ), [
            'type' => NotificationType::Subscription,
            'title' => 'Հաշվեկշիռը բավարար չէ',
            'url' => '/admin',
        ]);
        $balance->forceFill(['short_notified_for' => $ends])->save();
    }

    private function date(Tenant $tenant, Carbon $date): string
    {
        return $date->copy()->setTimezone($tenant->timezone ?: config('app.timezone'))->format('d.m.Y');
    }
}
