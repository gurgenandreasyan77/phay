<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Billing\Balance;
use App\Billing\BalancePayments;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A business's balance from the platform level (super admin only): topped
 * up when the business sends a receipt in the chat, which pays its open
 * request for AI agents and renews what ended; a correction fixes a wrong
 * top-up with a new ledger row (rows are never changed).
 */
class PlatformBalanceController extends Controller
{
    public function __construct(
        private readonly Balance $balance,
        private readonly BalancePayments $payments,
    ) {}

    public function show(Tenant $tenant): JsonResponse
    {
        return $this->present($tenant);
    }

    public function topUp(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->balance->topUp($tenant, $validated['amount'], $validated['note'] ?? null, $request->user());
        $this->payments->afterTopUp($tenant, $request->user());

        return $this->present($tenant, 201);
    }

    public function correct(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'between:-100000000,100000000', 'not_in:0'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $this->balance->correct($tenant, $validated['amount'], $validated['note'], $request->user());

        return $this->present($tenant, 201);
    }

    private function present(Tenant $tenant, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->payments->summary($tenant, platform: true, limit: 50)], $status);
    }
}
