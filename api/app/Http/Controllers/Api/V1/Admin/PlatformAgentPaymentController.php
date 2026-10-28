<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentTerms;
use App\Http\Controllers\Controller;
use App\Models\AgentPayment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * A business's AI agent payments from the platform level (super admin):
 * confirm a request once the money came, record one taken directly (some
 * months, or every chosen agent to one day), or take back a mistake.
 */
class PlatformAgentPaymentController extends Controller
{
    public function __construct(private readonly AgentTerms $terms) {}

    public function quote(Request $request, Tenant $tenant): JsonResponse
    {
        [$keys, $until, $months] = AgentBillingController::input($request, $tenant);

        return response()->json(['data' => $this->terms->quote($tenant, $keys, $until, $months)]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        [$keys, $until, $months] = AgentBillingController::input($request, $tenant);
        $validated = $request->validate([
            // Empty: what the quote says.
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->terms->record($tenant, $keys, $until, $months, $validated['amount'] ?? null, $validated['note'] ?? null, $request->user());

        return response()->json(['data' => $payment->present()], 201);
    }

    public function confirm(Request $request, Tenant $tenant, AgentPayment $payment): JsonResponse
    {
        abort_unless($payment->tenant_id === $tenant->id, 404);
        $validated = $request->validate([
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->terms->confirm($payment, $validated['amount'] ?? null, $validated['note'] ?? null, $request->user());
        } catch (InvalidArgumentException) {
            abort(422, 'Այս վճարումն արդեն գրանցված է։');
        }

        return response()->json(['data' => $payment->present()]);
    }

    /** A request: canceled. A recorded payment: only the latest, its agents go back. */
    public function destroy(Tenant $tenant, AgentPayment $payment): JsonResponse
    {
        abort_unless($payment->tenant_id === $tenant->id, 404);

        try {
            $payment->status === AgentPayment::REQUESTED
                ? $this->terms->cancelRequest($payment)
                : $this->terms->removePayment($payment);
        } catch (InvalidArgumentException) {
            abort(422, 'Կարելի է հեռացնել միայն վերջին վճարումը։');
        }

        return response()->json(['data' => ['deleted' => true]]);
    }
}
