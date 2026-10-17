<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TenantUserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Logins of a business (super admin only). The platform administrator
 * creates them by hand while onboarding a client: no invitation emails and
 * no self sign-up yet. Every login created here is the business's owner and
 * sees only that business.
 */
class TenantUserController extends Controller
{
    public function index(Tenant $tenant): AnonymousResourceCollection
    {
        return TenantUserResource::collection($tenant->users()->orderBy('users.id')->get());
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill(['is_super_admin' => false, 'current_tenant_id' => $tenant->id])->save();
            $user->tenants()->attach($tenant->id, ['role' => 'owner']);

            return $user;
        });

        return (new TenantUserResource($tenant->users()->findOrFail($user->id)))
            ->response()
            ->setStatusCode(201);
    }

    /** Renames the login or sets a new password (signs it out everywhere). */
    public function update(Request $request, Tenant $tenant, User $user): TenantUserResource
    {
        $this->ensureMember($tenant, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', Password::min(8)],
        ]);

        $user->fill($validated)->save();

        if (array_key_exists('password', $validated)) {
            $user->tokens()->delete();
        }

        return new TenantUserResource($tenant->users()->findOrFail($user->id));
    }

    /**
     * Takes the login's access to this business away. A login left with no
     * business is deleted; a platform administrator is only detached.
     */
    public function destroy(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->ensureMember($tenant, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Չեք կարող ջնջել ինքներդ ձեզ։']]);
        }

        DB::transaction(function () use ($tenant, $user) {
            $user->tenants()->detach($tenant->id);
            $user->tokens()->delete();

            if (! $user->is_super_admin && ! $user->tenants()->exists()) {
                $user->delete();
            } elseif ($user->current_tenant_id === $tenant->id) {
                $user->forceFill(['current_tenant_id' => null])->save();
            }
        });

        return response()->json(null, 204);
    }

    private function ensureMember(Tenant $tenant, User $user): void
    {
        abort_unless($tenant->users()->whereKey($user->getKey())->exists(), 404);
    }
}
