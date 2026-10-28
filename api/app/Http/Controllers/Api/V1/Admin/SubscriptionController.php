<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Agents\AgentTerms;
use App\Assistant\BusinessAssistant;
use App\Billing\BalancePayments;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SubscriptionPaymentResource;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\SupportChat;
use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The business's own subscription: what it pays (plan + modules it has on),
 * until when, its payments, and the other plans and modules with their
 * prices. Open even after the subscription ended, so the business sees how
 * to come back. Asking to renew or for a module goes to the chat.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly TenantContext $context,
    ) {}

    public function show(Request $request, BusinessAssistant $assistant, AgentTerms $terms, BalancePayments $balance): JsonResponse
    {
        $tenant = $this->context->current();
        $user = $request->user();
        $subscription = $this->subscriptions->for($tenant);
        $terms->sync($tenant);

        $payments = SubscriptionPayment::query()->where('tenant_id', $tenant->id)->latest('id')->limit(24)->get();

        return response()->json(['data' => [
            'subscription' => (new SubscriptionResource($subscription))->resolve(),
            // This subscription month's AI requests, shown next to what a bigger plan adds.
            'assistant' => $assistant->usage(),
            'payments' => SubscriptionPaymentResource::collection($payments)->resolve(),
            // Every AI agent on its own term, the open request to pay for some and their payments.
            'agentBilling' => $terms->billing($tenant),
            // The balance it pays from: for the people who manage the subscription.
            'balance' => ! $user->isPlatformStaff() && $user->businessRole($tenant)?->can('settings') ? $balance->summary($tenant) : null,
            'plans' => Plan::query()->where('is_active', true)->ordered()->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'assistantRequests' => $plan->assistant_requests,
                'includedModules' => $plan->included_modules ?? [],
            ]),
        ]]);
    }

    /**
     * Writes to the platform in the business's chat: renew, switch to a
     * plan, turn a module on (the platform does it and records payments),
     * ask for more AI assistant requests, ask about the premium plan, or
     * ask for an AI agent on the team.
     */
    public function request(Request $request, SupportChat $chat): JsonResponse
    {
        $user = $request->user();
        abort_if($user->isPlatformStaff(), 403, 'Platform staff answer from the platform level.');

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['renew', 'plan', 'module', 'premium', 'assistant', 'agent'])],
            'plan_id' => ['required_if:kind,plan', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'module' => ['required_if:kind,module', Rule::in(array_column(FeatureFlag::cases(), 'value'))],
            'agent' => ['required_if:kind,agent', Rule::in(AgentCatalog::keys())],
        ]);

        $body = match ($validated['kind']) {
            'renew' => 'Ցանկանում եմ երկարացնել բաժանորդագրությունը։ Խնդրում եմ ուղարկել վճարման մանրամասները։',
            'plan' => sprintf('Ցանկանում եմ անցնել «%s» փաթեթին։', Plan::query()->findOrFail($validated['plan_id'])->name),
            'module' => sprintf('Ցանկանում եմ միացնել «%s» մոդուլը։', FeatureFlag::from($validated['module'])->label()),
            'premium' => 'Հետաքրքրում է Պրեմիում փաթեթը։ Խնդրում եմ տեղեկացնել, երբ այն հասանելի լինի։',
            'assistant' => 'AI օգնականի այս ամսվա հարցերը սպառվել են։ Ցանկանում եմ ավելացնել հարցեր։',
            'agent' => sprintf('Ցանկանում եմ իմ թիմին ավելացնել «%s» AI աշխատակցին։', AgentCatalog::find($validated['agent'])->label()),
        };

        $chat->send($this->context->current(), $user, false, $body);

        return response()->json(['data' => ['sent' => true]], 201);
    }
}
