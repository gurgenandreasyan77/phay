<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessRole;
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
 * Logins of a business (platform staff only). Servio's people create them by
 * hand while onboarding a client: no invitation emails and no self sign-up
 * yet. Each login has a role in the business (owner, admin, moderator, see
 * BusinessRole) and sees only that business.
 *
 * Passwords are stored hashed and can't be shown again; a forgotten one is
 * replaced with a new one (update with `password`).
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
            'role' => ['sometimes', 'string', Rule::enum(BusinessRole::class)],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'is_super_admin' => false,
                'is_platform_moderator' => false,
                'current_tenant_id' => $tenant->id,
            ])->save();
            $user->tenants()->attach($tenant->id, ['role' => $validated['role'] ?? BusinessRole::Owner->value]);

            return $user;
        });

        return (new TenantUserResource($tenant->users()->findOrFail($user->id)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Renames the login, changes its role in this business, or sets a new
     * password (which signs it out everywhere).
     */
    public function update(Request $request, Tenant $tenant, User $user): TenantUserResource
    {
        $this->ensureMember($tenant, $user);
        $this->ensureMayManage($request, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', Password::min(8)],
            'role' => ['sometimes', 'required', 'string', Rule::enum(BusinessRole::class)],
        ]);

        DB::transaction(function () use ($tenant, $user, $validated) {
            $user->fill(array_intersect_key($validated, array_flip(['name', 'password'])))->save();

            if (array_key_exists('role', $validated)) {
                $tenant->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);
            }

            if (array_key_exists('password', $validated)) {
                $user->tokens()->delete();
            }
        });

        return new TenantUserResource($tenant->users()->findOrFail($user->id));
    }

    /**
     * Takes the login's access to this business away. A login left with no
     * business is deleted; platform staff are only detached.
     */
    public function destroy(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->ensureMember($tenant, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Չեք կարող ջնջել ինքներդ ձեզ։']]);
        }

        $this->ensureMayManage($request, $user);

        DB::transaction(function () use ($tenant, $user) {
            $user->tenants()->detach($tenant->id);
            $user->tokens()->delete();

            if (! $user->isPlatformStaff() && ! $user->tenants()->exists()) {
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

    /**
     * A platform moderator manages the business's own people only; accounts
     * of platform staff are the super admin's (see PlatformUserController).
     */
    private function ensureMayManage(Request $request, User $user): void
    {
        abort_if(
            $user->isPlatformStaff() && ! $request->user()->is_super_admin,
            403,
            'Only the super admin can change platform staff accounts.',
        );
    }
}
