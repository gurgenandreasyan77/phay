<?php

namespace App\Agents;

use App\Models\AgentPayment;
use App\Models\ModulePrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use App\Notifications\NotificationType;
use App\Services\SupportChat;
use App\Subscriptions\Subscriptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Each AI agent of a business runs on its own term: a free trial once
 * (7 days), then months the business pays for one agent at a time, so the
 * owner can take them on one by one. An agent its plan includes (or every
 * agent of an unlimited subscription) never ends.
 *
 * Paying always moves an agent's end forward: by some months from its own
 * end, or to one common day (the difference for each agent, by the day),
 * so all or some agents end — and are paid — on the same day. There is no
 * payment gateway: the owner asks (a request in the chat with the platform)
 * and the super admin records the money, which applies it.
 */
class AgentTerms
{
    /** Days of a month a part of one is priced by. */
    private const MONTH_DAYS = 30;

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly SupportChat $chat,
    ) {}

    public function trialDays(): int
    {
        return max(1, (int) config('platform.agent_trial_days', 7));
    }

    public function warnDays(): int
    {
        return max(1, (int) config('platform.agent_warn_days', 3));
    }

    /** Whether it never ends for this business: in the plan, or the subscription is unlimited. */
    public function included(Subscription $subscription, Agent $agent): bool
    {
        return $subscription->unlimited || (bool) $subscription->plan?->includes($agent->priceKey());
    }

    /** On the team and within its term (or included): it works. */
    public function running(Subscription $subscription, Agent $agent, ?TenantAgent $row): bool
    {
        if (! $row?->active) {
            return false;
        }

        return $this->included($subscription, $agent) || (bool) $row->coveredUntil()?->isFuture();
    }

    /**
     * Where one agent stands for the business, for its card and ring:
     * status off (not on the team), trial, active (paid), expired or
     * included; until when, the days left and the days the ring counts from.
     *
     * @return array{status: string, endsAt: string|null, daysLeft: int|null, span: int, ending: bool, trialAvailable: bool, trialEndsAt: string|null, paidUntil: string|null, price: int}
     */
    public function term(Subscription $subscription, Agent $agent, ?TenantAgent $row, int $price): array
    {
        $ends = $row?->coveredUntil();
        $included = $this->included($subscription, $agent);
        $left = $ends ? max(0, (int) ceil(now()->diffInSeconds($ends, false) / 86400)) : null;

        $status = match (true) {
            $included && (bool) $row?->active => 'included',
            ! $row?->active => 'off',
            $ends === null || ! $ends->isFuture() => 'expired',
            $row->paid_until?->isFuture() => 'active',
            default => 'trial',
        };

        $running = in_array($status, ['trial', 'active'], true);

        return [
            'status' => $status,
            'endsAt' => $status === 'included' ? null : $ends?->toIso8601String(),
            'daysLeft' => $status === 'included' ? null : $left,
            // A trial counts down from its days, a paid term from a month (or more, when paid ahead).
            'span' => max($left ?? 0, $status === 'trial' ? $this->trialDays() : self::MONTH_DAYS),
            'ending' => $running && $left !== null && $left <= $this->warnDays(),
            // The trial is given once: never on the team before.
            'trialAvailable' => ! $included && $row?->trial_ends_at === null && $row?->paid_until === null,
            'trialEndsAt' => $row?->trial_ends_at?->toIso8601String(),
            'paidUntil' => $row?->paid_until?->toIso8601String(),
            'price' => $price,
        ];
    }

    /**
     * Every agent's term for the business, by key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function terms(Tenant $tenant): array
    {
        $subscription = $this->subscriptions->for($tenant);
        $rows = $this->rows($tenant);
        $prices = ModulePrice::map();

        $terms = [];
        foreach (AgentCatalog::all() as $agent) {
            $terms[$agent->key()] = $this->term($subscription, $agent, $rows->get($agent->key()), $this->price($agent, $prices));
        }

        return $terms;
    }

    /**
     * The business's agents with their terms, the open payment request and
     * the latest recorded payments: the subscription page of both levels.
     *
     * @return array{agents: list<array<string, mixed>>, request: array<string, mixed>|null, payments: list<array<string, mixed>>, subscriptionEndsAt: string|null}
     */
    public function billing(Tenant $tenant): array
    {
        $terms = $this->terms($tenant);
        $payments = AgentPayment::query()->with(['requester:id,name', 'recorder:id,name'])->where('tenant_id', $tenant->id)->latest('id')->limit(25)->get();

        return [
            'agents' => array_values(array_map(fn (Agent $agent) => [
                'key' => $agent->key(),
                'persona' => $agent->persona(),
                'role' => $agent->role(),
                'label' => $agent->label(),
                ...$terms[$agent->key()],
            ], AgentCatalog::all())),
            'request' => $payments->firstWhere('status', AgentPayment::REQUESTED)?->present(),
            'payments' => $payments->where('status', AgentPayment::PAID)->values()->map(fn (AgentPayment $p) => $p->present())->all(),
            // A common end day the owner may pick: the business's own subscription's.
            'subscriptionEndsAt' => $this->subscriptions->for($tenant)->endsAt()?->toIso8601String(),
        ];
    }

    /**
     * Starts an agent's free trial: it joins the team for the trial days.
     * Only once per agent and business.
     */
    public function startTrial(Tenant $tenant, Agent $agent, ?User $by = null): TenantAgent
    {
        return DB::transaction(function () use ($tenant, $agent, $by) {
            $row = $this->row($tenant, $agent, lock: true);

            if ($row->trial_ends_at !== null || $row->paid_until !== null) {
                throw ValidationException::withMessages(['agent' => ['Այս AI աշխատակցի փորձաշրջանն արդեն օգտագործված է։']]);
            }

            $row->forceFill([
                'active' => true,
                'activated_at' => now(),
                'activated_by' => $by?->id,
                'trial_ends_at' => now()->addDays($this->trialDays()),
            ])->save();

            return $row;
        });
    }

    /**
     * What paying for some agents would cost and move: with `until`, every
     * one ends that day (plus `months` after it); without, each gets
     * `months` from its own end. An agent that ended starts from today.
     *
     * @param  list<string>  $keys
     * @return array{until: string|null, months: int, lines: list<array<string, mixed>>, total: int}
     */
    public function quote(Tenant $tenant, array $keys, ?Carbon $until, int $months): array
    {
        $subscription = $this->subscriptions->for($tenant);
        $rows = $this->rows($tenant);
        $prices = ModulePrice::map();
        $agents = $this->agents($keys);

        if ($until === null && $months < 1) {
            throw ValidationException::withMessages(['months' => ['Նշեք ամիսների քանակը կամ ընդհանուր օրը։']]);
        }

        $lines = [];
        foreach ($agents as $agent) {
            if ($this->included($subscription, $agent)) {
                throw ValidationException::withMessages(['agents' => [sprintf('«%s»-ն ներառված է փաթեթում. նրա համար վճարել պետք չէ։', $agent->label())]]);
            }

            $covered = $rows->get($agent->key())?->coveredUntil();
            $from = $covered && $covered->isFuture() ? $covered->copy() : now();

            if ($until !== null && $from->gt($until)) {
                throw ValidationException::withMessages(['until' => [sprintf(
                    '«%s»-ն արդեն վճարված է մինչև %s. ընտրեք այդ կամ ավելի ուշ օր։',
                    $agent->label(),
                    $this->date($tenant, $from),
                )]]);
            }

            $to = ($until ?? $from)->copy()->addMonthsNoOverflow($months);
            $price = $this->price($agent, $prices);

            $lines[] = [
                'agent' => $agent->key(),
                'label' => $agent->label(),
                'price' => $price,
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => (int) ceil($from->diffInSeconds($to) / 86400),
                'amount' => self::amount($price, $from, $to),
            ];
        }

        return [
            'until' => $until?->toIso8601String(),
            'months' => $months,
            'lines' => $lines,
            'total' => array_sum(array_column($lines, 'amount')),
        ];
    }

    /**
     * The owner asks to pay (there is no gateway): the quote is kept and
     * sent to the platform in the chat; the super admin confirms it once
     * the money came. A new request replaces an earlier open one.
     *
     * @param  list<string>  $keys
     */
    public function request(Tenant $tenant, User $by, array $keys, ?Carbon $until, int $months): AgentPayment
    {
        $quote = $this->quote($tenant, $keys, $until, $months);

        return DB::transaction(function () use ($tenant, $by, $quote, $until, $months) {
            AgentPayment::query()->where('tenant_id', $tenant->id)->where('status', AgentPayment::REQUESTED)->delete();

            $payment = AgentPayment::create([
                'tenant_id' => $tenant->id,
                'status' => AgentPayment::REQUESTED,
                'amount' => $quote['total'],
                'until' => $until,
                'months' => $months,
                'lines' => $this->stored($quote['lines']),
                'requested_by' => $by->id,
            ]);

            $this->chat->send($tenant, $by, false, $this->requestMessage($tenant, $quote, $until));

            return $payment;
        });
    }

    /** The owner or the platform takes back a request that wasn't paid. */
    public function cancelRequest(AgentPayment $payment): void
    {
        if ($payment->status !== AgentPayment::REQUESTED) {
            throw new InvalidArgumentException('Only a request can be canceled.');
        }

        $payment->delete();
    }

    /**
     * The super admin records that a request was paid (the amount may be
     * corrected): every agent of it moves to its end.
     */
    public function confirm(AgentPayment $payment, ?int $amount, ?string $note, ?User $by): AgentPayment
    {
        if ($payment->status !== AgentPayment::REQUESTED) {
            throw new InvalidArgumentException('This payment is already recorded.');
        }

        return DB::transaction(function () use ($payment, $amount, $note, $by) {
            $payment->forceFill([
                'amount' => $amount ?? $payment->amount,
                'note' => $note ?? $payment->note,
            ]);

            return $this->apply($payment, $by);
        });
    }

    /**
     * A payment the super admin takes in directly (cash, a transfer):
     * quoted like a request and applied at once.
     *
     * @param  list<string>  $keys
     */
    public function record(Tenant $tenant, array $keys, ?Carbon $until, int $months, ?int $amount, ?string $note, ?User $by): AgentPayment
    {
        $quote = $this->quote($tenant, $keys, $until, $months);

        return DB::transaction(function () use ($tenant, $quote, $until, $months, $amount, $note, $by) {
            $payment = new AgentPayment([
                'tenant_id' => $tenant->id,
                'amount' => $amount ?? $quote['total'],
                'until' => $until,
                'months' => $months,
                'lines' => $this->stored($quote['lines']),
                'note' => $note,
            ]);

            return $this->apply($payment, $by);
        });
    }

    /**
     * Takes back the latest recorded payment (entered by mistake): each of
     * its agents goes back to the paid end it had before.
     */
    public function removePayment(AgentPayment $payment): void
    {
        $latest = AgentPayment::query()->where('tenant_id', $payment->tenant_id)->where('status', AgentPayment::PAID)->latest('id')->first();

        if (! $latest || ! $latest->is($payment)) {
            throw new InvalidArgumentException('Only the latest payment can be removed.');
        }

        DB::transaction(function () use ($payment) {
            foreach ($payment->lines as $line) {
                TenantAgent::withoutTenancy()
                    ->where('tenant_id', $payment->tenant_id)
                    ->where('agent_key', $line['agent'])
                    ->update(['paid_until' => $line['previous_paid_until'] ?? null]);
            }
            $payment->delete();
        });
    }

    /**
     * Tells the business, once per end date, which agents end within the
     * warning days and which have ended (one message for all of them).
     */
    public function sync(Tenant $tenant): void
    {
        $subscription = $this->subscriptions->for($tenant);
        if ($subscription->unlimited) {
            return;
        }

        $ending = [];
        $ended = [];

        foreach ($this->rows($tenant) as $row) {
            $agent = $row->agent();
            $ends = $row->coveredUntil();
            if (! $agent || ! $row->active || ! $ends || $this->included($subscription, $agent)) {
                continue;
            }

            if (! $ends->isFuture()) {
                if (! $this->same($ends, $row->expired_notified_for)) {
                    $ended[] = $agent;
                    $row->forceFill(['expired_notified_for' => $ends, 'warned_for' => $ends])->save();
                }
            } elseif ($ends->lte(now()->addDays($this->warnDays())) && ! $this->same($ends, $row->warned_for)) {
                $ending[] = [$agent, $ends];
                $row->forceFill(['warned_for' => $ends])->save();
            }
        }

        if ($ending !== []) {
            $names = implode(', ', array_map(fn (array $pair) => sprintf('%s (%s)', $pair[0]->label(), $this->date($tenant, $pair[1])), $ending));
            $this->chat->notify($tenant, sprintf(
                'Շուտով ավարտվում է AI աշխատակիցների ժամկետը՝ %s։ Երկարացնել կարող եք «Բաժանորդագրություն» էջում. այնտեղ կարող եք նաև բոլորի ժամկետը բերել մեկ օրի։',
                $names,
            ), [
                'type' => NotificationType::Agent,
                'title' => count($ending) === 1
                    ? sprintf('%s-ի ժամկետն ավարտվում է', $ending[0][0]->persona()['hy'])
                    : 'AI աշխատակիցների ժամկետն ավարտվում է',
                'url' => '/admin/subscription#agents',
            ]);
        }

        if ($ended !== []) {
            $names = implode(', ', array_map(fn (Agent $agent) => $agent->label(), $ended));
            $this->chat->notify($tenant, sprintf(
                'Ավարտվեց AI աշխատակիցների ժամկետը՝ %s։ Նրանց հիշողությունը և զրույցները պահպանված են. վճարելուց հետո նրանք կշարունակեն աշխատել։',
                $names,
            ), [
                'type' => NotificationType::Agent,
                'title' => 'AI աշխատակցի ժամկետն ավարտվեց',
                'url' => '/admin/subscription#agents',
            ]);
        }
    }

    /**
     * What a stretch of an agent costs: its price for each whole month, and
     * by the day (a 30th of it) for the rest, rounded up to 10 dram.
     */
    public static function amount(int $price, Carbon $from, Carbon $to): int
    {
        if ($price <= 0 || ! $to->gt($from)) {
            return 0;
        }

        $months = 0;
        $cursor = $from->copy();
        while ($cursor->copy()->addMonthsNoOverflow(1)->lte($to)) {
            $cursor = $cursor->addMonthsNoOverflow(1);
            $months++;
        }

        $days = (int) ceil($cursor->diffInSeconds($to) / 86400);
        $part = (int) (ceil(min($price, $price * $days / self::MONTH_DAYS) / 10) * 10);

        return $price * $months + $part;
    }

    /** @param  array<string, int>|null  $prices  module prices, loaded once for a list */
    public function price(Agent $agent, ?array $prices = null): int
    {
        $prices ??= ModulePrice::map();

        return (int) ($prices[$agent->priceKey()] ?? $agent->defaultPrice());
    }

    private function apply(AgentPayment $payment, ?User $by): AgentPayment
    {
        $tenant = $payment->tenant ?? Tenant::query()->findOrFail($payment->tenant_id);
        $lines = [];

        foreach ($payment->lines as $line) {
            $agent = AgentCatalog::find($line['agent']);
            if (! $agent) {
                continue;
            }
            $row = $this->row($tenant, $agent, lock: true);
            $to = Carbon::parse($line['to']);

            $lines[] = [...$line, 'previous_paid_until' => $row->paid_until?->toIso8601String()];
            $row->forceFill([
                'active' => true,
                'activated_at' => $row->active ? $row->activated_at : now(),
                'activated_by' => $row->active ? $row->activated_by : $by?->id,
                'paid_until' => $row->paid_until && $row->paid_until->gt($to) ? $row->paid_until : $to,
            ])->save();
        }

        $payment->forceFill([
            'status' => AgentPayment::PAID,
            'lines' => $lines,
            'recorded_by' => $by?->id,
            'paid_at' => now(),
        ])->save();

        $this->chat->notify($tenant, sprintf(
            'Շնորհակալություն, AI աշխատակիցների վճարումը ստացված է (%s դր)։ %s',
            $this->money($payment->amount),
            implode('; ', array_map(
                fn (array $line) => sprintf('%s՝ մինչև %s', AgentCatalog::find($line['agent'])?->label() ?? $line['agent'], $this->date($tenant, Carbon::parse($line['to']))),
                $lines,
            )).'։',
        ), [
            'type' => NotificationType::Agent,
            'title' => 'AI աշխատակիցների վճարումը ստացված է',
            'url' => '/admin/subscription#agents',
        ]);

        return $payment;
    }

    /** @param  array<string, mixed>  $quote */
    private function requestMessage(Tenant $tenant, array $quote, ?Carbon $until): string
    {
        $lines = array_map(fn (array $line) => sprintf(
            '• %s՝ մինչև %s (%d օր) — %s դր',
            $line['label'],
            $this->date($tenant, Carbon::parse($line['to'])),
            $line['days'],
            $this->money($line['amount']),
        ), $quote['lines']);

        $lead = $until !== null && count($quote['lines']) > 1
            ? 'Ցանկանում եմ վճարել AI աշխատակիցների համար, որպեսզի նրանց ժամկետն ավարտվի նույն օրը։'
            : 'Ցանկանում եմ վճարել AI աշխատակիցների համար։';

        return $lead."\n".implode("\n", $lines)."\n".sprintf('Ընդամենը՝ %s դր։ Խնդրում եմ ուղարկել վճարման մանրամասները։', $this->money($quote['total']));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function stored(array $lines): array
    {
        return array_map(fn (array $line) => [
            'agent' => $line['agent'],
            'from' => $line['from'],
            'to' => $line['to'],
            'days' => $line['days'],
            'amount' => $line['amount'],
        ], $lines);
    }

    /**
     * @param  list<string>  $keys
     * @return list<Agent>
     */
    private function agents(array $keys): array
    {
        $agents = [];
        foreach (array_unique($keys) as $key) {
            $agents[] = AgentCatalog::find($key) ?? throw ValidationException::withMessages(['agents' => ['Անհայտ AI աշխատակից։']]);
        }

        if ($agents === []) {
            throw ValidationException::withMessages(['agents' => ['Ընտրեք գոնե մեկ AI աշխատակից։']]);
        }

        return $agents;
    }

    /** @return Collection<string, TenantAgent> */
    private function rows(Tenant $tenant): Collection
    {
        return TenantAgent::withoutTenancy()->where('tenant_id', $tenant->id)->get()->keyBy('agent_key');
    }

    private function row(Tenant $tenant, Agent $agent, bool $lock = false): TenantAgent
    {
        // Another request may make it at the same moment: whichever comes first stands.
        TenantAgent::withoutTenancy()->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'agent_key' => $agent->key(),
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TenantAgent::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('agent_key', $agent->key())
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    private function same(Carbon $a, ?Carbon $b): bool
    {
        return $b !== null && $a->timestamp === $b->timestamp;
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
