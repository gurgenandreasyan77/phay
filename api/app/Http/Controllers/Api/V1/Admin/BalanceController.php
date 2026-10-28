<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Billing\Balance;
use App\Billing\BalancePayments;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The business's own balance: what it holds, what is coming and its
 * history; whether what ends renews from it on its own; and paying from it
 * for months of the subscription, a bigger plan, a module or AI agents.
 * The owner and the admin (settings), never platform staff: the super admin
 * tops it up from the platform level. Open after the subscription ended,
 * so the business can come back by paying from it.
 */
class BalanceController extends Controller
{
    public function __construct(
        private readonly Balance $balance,
        private readonly BalancePayments $payments,
        private readonly TenantContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return $this->present();
    }

    public function update(Request $request): JsonResponse
    {
        $this->allow($request);
        $validated = $request->validate(['auto_renew' => ['required', 'boolean']]);

        $this->balance->setAutoRenew($this->context->current(), $validated['auto_renew']);

        return $this->present();
    }

    public function subscription(Request $request): JsonResponse
    {
        $this->allow($request);
        $validated = $request->validate(['months' => ['required', 'integer', 'min:1', 'max:12']]);

        $this->payments->renewSubscription($this->context->current(), $validated['months'], $request->user());

        return $this->present(201);
    }

    public function plan(Request $request): JsonResponse
    {
        $this->allow($request);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ]);

        $this->payments->upgradePlan($this->context->current(), Plan::query()->findOrFail($validated['plan_id']), $request->user());

        return $this->present(201);
    }

    public function module(Request $request): JsonResponse
    {
        $this->allow($request);
        $validated = $request->validate([
            'module' => ['required', Rule::in(array_column(FeatureFlag::cases(), 'value'))],
        ]);

        $this->payments->enableModule($this->context->current(), FeatureFlag::from($validated['module']), $request->user());

        return $this->present(201);
    }

    public function agents(Request $request): JsonResponse
    {
        $this->allow($request);
        $tenant = $this->context->current();
        [$keys, $until, $months] = AgentBillingController::input($request, $tenant);

        $this->payments->buyAgents($tenant, $keys, $until, $months, $request->user());

        return $this->present(201);
    }

    private function present(int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->payments->summary($this->context->current())], $status);
    }

    private function allow(Request $request): void
    {
        abort_if($request->user()->isPlatformStaff(), 403, 'Platform staff top the balance up from the platform level.');
    }
}
