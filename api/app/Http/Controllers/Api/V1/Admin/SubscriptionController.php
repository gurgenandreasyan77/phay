<?php

namespace App\Http\Controllers\Api\V1\Admin;

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

    public function show(): JsonResponse
    {
        $tenant = $this->context->current();
        $subscription = $this->subscriptions->for($tenant);

        $payments = SubscriptionPayment::query()->where('tenant_id', $tenant->id)->latest('id')->limit(24)->get();

        return response()->json(['data' => [
            'subscription' => (new SubscriptionResource($subscription))->resolve(),
            'payments' => SubscriptionPaymentResource::collection($payments)->resolve(),
            'plans' => Plan::query()->where('is_active', true)->ordered()->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'includedModules' => $plan->included_modules ?? [],
            ]),
        ]]);
    }

    /**
     * Writes to the platform in the business's chat: renew, switch to a
     * plan, or turn a module on (the platform does it and records payments).
     */
    public function request(Request $request, SupportChat $chat): JsonResponse
    {
        $user = $request->user();
        abort_if($user->isPlatformStaff(), 403, 'Platform staff answer from the platform level.');

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['renew', 'plan', 'module'])],
            'plan_id' => ['required_if:kind,plan', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'module' => ['required_if:kind,module', Rule::in(array_column(FeatureFlag::cases(), 'value'))],
        ]);

        $body = match ($validated['kind']) {
            'renew' => 'Ցանկանում եմ երկարացնել բաժանորդագրությունը։ Խնդրում եմ ուղարկել վճարման մանրամասները։',
            'plan' => sprintf('Ցանկանում եմ անցնել «%s» փաթեթին։', Plan::query()->findOrFail($validated['plan_id'])->name),
            'module' => sprintf('Ցանկանում եմ միացնել «%s» մոդուլը։', FeatureFlag::from($validated['module'])->label()),
        };

        $chat->send($this->context->current(), $user, false, $body);

        return response()->json(['data' => ['sent' => true]], 201);
    }
}
