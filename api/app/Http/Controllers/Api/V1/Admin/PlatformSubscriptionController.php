<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentTerms;
use App\Assistant\AssistantQuota;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SubscriptionPaymentResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Models\AssistantBonus;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Subscriptions\Subscriptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Every business's subscription, from the platform level (super admin
 * only): the list with who pays what and until when, and on one business:
 * its plan, payments recorded by hand, free access, turning it off (after a
 * notice) and back on, and extra AI assistant requests given by hand.
 */
class PlatformSubscriptionController extends Controller
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly AssistantQuota $quota,
        private readonly AgentTerms $terms,
    ) {}

    /** ?status= keeps one status (trial, active, free, canceling, expired, unlimited). */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $prices = ModulePrice::map();

        $rows = Tenant::query()
            ->with(['subscription.plan'])
            ->orderBy('id')
            ->get()
            ->map(function (Tenant $tenant) use ($prices) {
                $subscription = $this->subscriptions->sync($tenant, $this->subscriptions->for($tenant));
                $subscription->setRelation('tenant', $tenant);
                $last = SubscriptionPayment::query()->where('tenant_id', $tenant->id)->latest('id')->first();

                return [
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'logo' => $tenant->logo,
                        'status' => $tenant->status,
                    ],
                    'subscription' => (new SubscriptionResource($subscription, $prices))->resolve(),
                    'lastPayment' => $last ? (new SubscriptionPaymentResource($last))->resolve() : null,
                ];
            })
            ->when(
                is_string($status) && SubscriptionStatus::tryFrom($status),
                fn ($rows) => $rows->filter(fn ($row) => $row['subscription']['status'] === $status),
            )
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return $this->present($tenant);
    }

    /** The plan, or unlimited access (e.g. a partner who never pays). */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['sometimes', 'integer', Rule::exists('plans', 'id')],
            'unlimited' => ['sometimes', 'boolean'],
        ]);

        $subscription = $this->subscriptions->for($tenant);

        if (isset($validated['plan_id'])) {
            $this->subscriptions->changePlan($subscription, Plan::query()->findOrFail($validated['plan_id']));
        }
        if (array_key_exists('unlimited', $validated)) {
            $this->subscriptions->setUnlimited($subscription, $validated['unlimited']);
        }

        return $this->present($tenant);
    }

    public function storePayment(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            // Empty: the monthly price × months.
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subscriptions->recordPayment(
            $this->subscriptions->for($tenant),
            $validated['months'],
            $validated['amount'] ?? null,
            $validated['note'] ?? null,
            $request->user(),
        );

        return $this->present($tenant, 201);
    }

    /** Only the latest payment (one entered by mistake). */
    public function destroyPayment(Tenant $tenant, SubscriptionPayment $payment): JsonResponse
    {
        abort_unless($payment->tenant_id === $tenant->id, 404);

        try {
            $this->subscriptions->deletePayment($this->subscriptions->for($tenant), $payment);
        } catch (InvalidArgumentException) {
            abort(422, 'Կարելի է հեռացնել միայն վերջին վճարումը։');
        }

        return $this->present($tenant);
    }

    /** Free access for some days, weeks or months, after the current end. */
    public function grantFree(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:365'],
            'unit' => ['required', Rule::in(Subscriptions::UNITS)],
        ]);

        $this->subscriptions->grantFree($this->subscriptions->for($tenant), $validated['amount'], $validated['unit'], $request->user());

        return $this->present($tenant);
    }

    public function removeFree(Tenant $tenant): JsonResponse
    {
        $this->subscriptions->removeFree($this->subscriptions->for($tenant));

        return $this->present($tenant);
    }

    /** Turns it off after a notice (the business is told now); 0 days: right away. */
    public function cancel(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ]);

        $this->subscriptions->cancel($this->subscriptions->for($tenant), $validated['days'] ?? $this->subscriptions->warnDays());

        return $this->present($tenant);
    }

    public function resume(Tenant $tenant): JsonResponse
    {
        $this->subscriptions->resume($this->subscriptions->for($tenant));

        return $this->present($tenant);
    }

    /**
     * Extra AI assistant requests (a negative amount takes back unspent
     * ones). They don't expire; the business is told in its chat.
     */
    public function grantAssistantBonus(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $bonus = $this->quota->grantBonus($tenant, $validated['amount'], $validated['note'] ?? null, $request->user());

        if ($bonus->amount > 0) {
            $this->subscriptions->tell($tenant, sprintf(
                'Ձեզ տրամադրվել է AI օգնականի +%d հավելյալ հարց։ Դրանք չեն այրվում և օգտագործվում են փաթեթի ամսական հարցերից հետո։',
                $bonus->amount,
            ));
        }

        return $this->present($tenant, 201);
    }

    private function present(Tenant $tenant, int $status = 200): JsonResponse
    {
        $subscription = $this->subscriptions->sync($tenant, $this->subscriptions->for($tenant)->refresh());
        $subscription->setRelation('tenant', $tenant);

        $payments = SubscriptionPayment::query()->with('user:id,name')->where('tenant_id', $tenant->id)->latest('id')->limit(50)->get();

        return response()->json(['data' => [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'logo' => $tenant->logo, 'status' => $tenant->status],
            'subscription' => (new SubscriptionResource($subscription))->resolve(),
            'payments' => SubscriptionPaymentResource::collection($payments)->resolve(),
            // Every AI agent on its own term, the business's open request and agent payments.
            'agentBilling' => $this->terms->billing($tenant),
            // AI assistant requests this subscription month and the bonuses given.
            'assistant' => [
                ...$this->quota->status($tenant),
                'bonuses' => AssistantBonus::query()->with('user:id,name')->where('tenant_id', $tenant->id)->latest('id')->limit(20)->get()
                    ->map(fn (AssistantBonus $b) => [
                        'id' => $b->id,
                        'amount' => $b->amount,
                        'note' => $b->note,
                        'givenBy' => $b->user?->name,
                        'createdAt' => $b->created_at?->toIso8601String(),
                    ]),
            ],
            'plans' => Plan::query()->ordered()->get(['id', 'name', 'price', 'is_active', 'assistant_requests'])->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'isActive' => $plan->is_active,
                'assistantRequests' => $plan->assistant_requests,
            ]),
        ]], $status);
    }
}
