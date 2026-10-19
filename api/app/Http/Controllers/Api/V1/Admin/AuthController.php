<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::once($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isSuperAdmin' => $user->is_super_admin,
                    'platformRole' => $user->platformRole()?->value,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function me(Request $request, TenantContext $context): JsonResponse
    {
        return response()->json(['data' => $this->presentMe($request->user(), $context->current())]);
    }

    /**
     * Makes another business the one the admin panel works on. Only a
     * business the user may access (any business for platform staff).
     */
    public function switchTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $user = $request->user();
        $tenant = Tenant::query()->findOrFail($validated['tenant_id']);

        abort_unless($user->canAccessTenant($tenant), 403, 'You have no access to this business.');

        $user->forceFill(['current_tenant_id' => $tenant->id])->save();
        $user->setRelation('currentTenant', $tenant);

        return response()->json(['data' => $this->presentMe($user, $tenant)]);
    }

    private function presentMe(User $user, Tenant $current): array
    {
        $role = $user->businessRole($current);

        // Platform staff can switch to any business; everyone else to theirs.
        $tenants = $user->isPlatformStaff()
            ? Tenant::query()->orderBy('id')->get()
            : $user->tenants()->orderBy('tenants.id')->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isSuperAdmin' => $user->is_super_admin,
            'platformRole' => $user->platformRole()?->value,
            // The role in the current business and what it allows there
            // (platform staff act as the owner).
            'businessRole' => $role?->value,
            'permissions' => $role?->permissions() ?? [],
            'currentTenant' => new TenantResource($current),
            // The current business's subscription: a banner before it ends, a
            // lock screen after (never for platform staff).
            'subscription' => $this->subscriptionSummary($user, $current),
            'tenants' => TenantResource::collection($tenants),
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionSummary(User $user, Tenant $tenant): array
    {
        $subscriptions = app(Subscriptions::class);
        $subscription = $subscriptions->for($tenant);
        $status = $subscription->status();

        return [
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'endsAt' => $subscription->endsAt()?->toIso8601String(),
            'daysLeft' => $subscription->daysLeft(),
            'ending' => $subscription->isEnding($subscriptions->warnDays()),
            'locked' => ! $status->allowsAccess() && ! $user->isPlatformStaff(),
        ];
    }
}
