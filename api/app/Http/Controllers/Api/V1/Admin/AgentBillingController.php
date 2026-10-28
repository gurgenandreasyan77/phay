<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Agents\AgentTerms;
use App\Http\Controllers\Controller;
use App\Models\AgentPayment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The business pays for its AI agents one by one (AgentTerms): starts an
 * agent's free trial, sees what some months or one common end day would
 * cost, and asks the platform to pay (the super admin records the money).
 * The owner and the admin (settings), never platform staff.
 */
class AgentBillingController extends Controller
{
    public function __construct(
        private readonly AgentTerms $terms,
        private readonly TenantContext $context,
    ) {}

    public function trial(Request $request, Agents $agents, string $agent): JsonResponse
    {
        $this->allow($request);
        $found = AgentCatalog::find($agent) ?? abort(404);
        $tenant = $this->context->current();

        $agents->startTrial($tenant, $found, $request->user());

        return response()->json(['data' => collect($agents->overview($tenant))->firstWhere('key', $found->key())], 201);
    }

    public function quote(Request $request): JsonResponse
    {
        $this->allow($request);
        [$keys, $until, $months] = self::input($request, $this->context->current());

        return response()->json(['data' => $this->terms->quote($this->context->current(), $keys, $until, $months)]);
    }

    public function request(Request $request): JsonResponse
    {
        $this->allow($request);
        $tenant = $this->context->current();
        [$keys, $until, $months] = self::input($request, $tenant);

        $payment = $this->terms->request($tenant, $request->user(), $keys, $until, $months);

        return response()->json(['data' => $payment->present()], 201);
    }

    public function cancel(Request $request, AgentPayment $payment): JsonResponse
    {
        $this->allow($request);
        abort_unless($payment->tenant_id === $this->context->current()->id && $payment->status === AgentPayment::REQUESTED, 404);

        $this->terms->cancelRequest($payment);

        return response()->json(['data' => ['canceled' => true]]);
    }

    /**
     * The agents, a common end day (the end of that day in the business's
     * time zone) and the months after it, as the API takes them.
     *
     * @return array{0: list<string>, 1: Carbon|null, 2: int}
     */
    public static function input(Request $request, Tenant $tenant): array
    {
        $validated = $request->validate([
            'agents' => ['required', 'array', 'min:1', 'max:'.count(AgentCatalog::all())],
            'agents.*' => ['string', Rule::in(AgentCatalog::keys())],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+3 years'],
            'months' => ['sometimes', 'integer', 'min:0', 'max:12'],
        ]);

        $zone = $tenant->timezone ?: config('app.timezone');
        $until = isset($validated['until']) ? Carbon::createFromFormat('Y-m-d', $validated['until'], $zone)->endOfDay()->utc() : null;

        return [array_values($validated['agents']), $until, (int) ($validated['months'] ?? ($until ? 0 : 1))];
    }

    private function allow(Request $request): void
    {
        abort_if($request->user()->isPlatformStaff(), 403, 'Platform staff record payments from the platform level.');
    }
}
