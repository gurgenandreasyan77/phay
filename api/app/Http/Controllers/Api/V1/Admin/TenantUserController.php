<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessRole;
use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TenantUserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Security\AccountLinks;
use App\Security\SecurityLog;
use App\Security\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Logins of a business (platform staff only). Servio's people create them
 * while onboarding a client (no self sign-up). Each login has a role in the
 * business (owner, admin, moderator, see BusinessRole) and sees only that
 * business.
 *
 * Staff never set or see a password: a new login gets a one-time invitation
 * link (emailed, and returned to pass on by messenger) to set its own; a
 * forgotten one gets a reset link. Staff can also reset the 2FA of someone
 * who lost their phone.
 */
class TenantUserController extends Controller
{
    public function index(Tenant $tenant): AnonymousResourceCollection
    {
        return TenantUserResource::collection($tenant->users()->orderBy('users.id')->get());
    }

    public function __construct(
        private readonly AccountLinks $links,
        private readonly SecurityLog $log,
    ) {}

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['sometimes', 'string', Rule::enum(BusinessRole::class)],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => null,
            ]);
            $user->forceFill([
                'is_super_admin' => false,
                'is_platform_moderator' => false,
                'current_tenant_id' => $tenant->id,
            ])->save();
            $user->tenants()->attach($tenant->id, ['role' => $validated['role'] ?? BusinessRole::Owner->value]);

            return $user;
        });

        $this->log->record(SecurityEvent::LoginCreated, $user, [
            'tenant_id' => $tenant->id,
            'role' => $validated['role'] ?? BusinessRole::Owner->value,
        ], $request->user());

        return (new TenantUserResource($tenant->users()->findOrFail($user->id)))
            ->additional(['invitation' => $this->links->invite($user, $request->user())])
            ->response()
            ->setStatusCode(201);
    }

    /** A new invitation link for a login that hasn't set its password yet. */
    public function invitation(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->ensureMember($tenant, $user);
        $this->ensureMayManage($request, $user);

        if ($user->isActivated()) {
            throw ValidationException::withMessages(['user' => ['Այս մուտքն արդեն ակտիվ է։ Օգտագործեք գաղտնաբառի վերականգնումը։']]);
        }

        return response()->json(['data' => $this->links->invite($user, $request->user())]);
    }

    /** A password reset link for someone who forgot theirs. */
    public function passwordReset(Request $request, Tenant $tenant, User $user): JsonResponse
    {
        $this->ensureMember($tenant, $user);
        $this->ensureMayManage($request, $user);

        if (! $user->isActivated()) {
            throw ValidationException::withMessages(['user' => ['Մուտքը դեռ ակտիվ չէ։ Ուղարկեք նոր հրավեր։']]);
        }

        return response()->json(['data' => $this->links->passwordReset($user, $request->user())]);
    }

    /** Lost phone and recovery codes: 2FA off, every session out; set up again at the next sign-in. */
    public function resetTwoFactor(Request $request, Tenant $tenant, User $user, TwoFactor $twoFactor): JsonResponse
    {
        $this->ensureMember($tenant, $user);
        $this->ensureMayManage($request, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Ձեր սեփական 2FA-ն փոխեք «Իմ հաշիվը» էջում։']]);
        }

        $twoFactor->disable($user, $request->user(), 'staff');

        return response()->json(['data' => ['status' => 'ok']]);
    }

    /** Renames the login or changes its role in this business. */
    public function update(Request $request, Tenant $tenant, User $user): TenantUserResource
    {
        $this->ensureMember($tenant, $user);
        $this->ensureMayManage($request, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', 'string', Rule::enum(BusinessRole::class)],
        ]);

        DB::transaction(function () use ($request, $tenant, $user, $validated) {
            $user->fill(array_intersect_key($validated, array_flip(['name'])))->save();

            if (array_key_exists('role', $validated)) {
                $tenant->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);
                $this->log->record(SecurityEvent::LoginRoleChanged, $user, [
                    'tenant_id' => $tenant->id,
                    'role' => $validated['role'],
                ], $request->user());
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

        $this->log->record(SecurityEvent::LoginRemoved, $user, ['tenant_id' => $tenant->id], $request->user());

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
