<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlatformRole;
use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlatformUserResource;
use App\Models\User;
use App\Security\AccountLinks;
use App\Security\SecurityLog;
use App\Security\TwoFactor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Servio's own team on the platform (super admin only): other super admins
 * and moderators who onboard clients. Like business logins they get an
 * invitation link to set their own password, and they must use 2FA.
 */
class PlatformUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlatformUserResource::collection($this->staff()->orderBy('id')->get());
    }

    public function __construct(
        private readonly AccountLinks $links,
        private readonly SecurityLog $log,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::enum(PlatformRole::class)],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => null,
            ]);
            $this->assignRole($user, PlatformRole::from($validated['role']));

            return $user;
        });

        $this->log->record(SecurityEvent::LoginCreated, $user, ['platform_role' => $validated['role']], $request->user());

        return (new PlatformUserResource($user))
            ->additional(['invitation' => $this->links->invite($user, $request->user())])
            ->response()
            ->setStatusCode(201);
    }

    public function invitation(Request $request, User $user): JsonResponse
    {
        $this->ensureStaff($user);

        if ($user->isActivated()) {
            throw ValidationException::withMessages(['user' => ['Այս մուտքն արդեն ակտիվ է։ Օգտագործեք գաղտնաբառի վերականգնումը։']]);
        }

        return response()->json(['data' => $this->links->invite($user, $request->user())]);
    }

    public function passwordReset(Request $request, User $user): JsonResponse
    {
        $this->ensureStaff($user);

        if (! $user->isActivated()) {
            throw ValidationException::withMessages(['user' => ['Մուտքը դեռ ակտիվ չէ։ Ուղարկեք նոր հրավեր։']]);
        }

        return response()->json(['data' => $this->links->passwordReset($user, $request->user())]);
    }

    public function resetTwoFactor(Request $request, User $user, TwoFactor $twoFactor): JsonResponse
    {
        $this->ensureStaff($user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Ձեր սեփական 2FA-ն փոխեք «Իմ հաշիվը» էջում։']]);
        }

        $twoFactor->disable($user, $request->user(), 'staff');

        return response()->json(['data' => ['status' => 'ok']]);
    }

    /** Renames or changes the role. */
    public function update(Request $request, User $user): PlatformUserResource
    {
        $this->ensureStaff($user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', 'string', Rule::enum(PlatformRole::class)],
        ]);

        if (isset($validated['role']) && $user->is($request->user()) && $validated['role'] !== PlatformRole::SuperAdmin->value) {
            throw ValidationException::withMessages(['role' => ['Չեք կարող փոխել ձեր սեփական դերը։']]);
        }

        DB::transaction(function () use ($request, $user, $validated) {
            $user->fill(array_intersect_key($validated, array_flip(['name'])))->save();

            if (isset($validated['role'])) {
                $this->assignRole($user, PlatformRole::from($validated['role']));
                $this->log->record(SecurityEvent::LoginRoleChanged, $user, ['platform_role' => $validated['role']], $request->user());
            }
        });

        return new PlatformUserResource($user->fresh());
    }

    /**
     * Takes platform rights away. Someone who is also a member of a business
     * keeps that login; anyone else is deleted.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureStaff($user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['Չեք կարող ջնջել ինքներդ ձեզ։']]);
        }

        $this->log->record(SecurityEvent::LoginRemoved, $user, ['platform' => true], $request->user());

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            if ($user->tenants()->exists()) {
                $user->forceFill(['is_super_admin' => false, 'is_platform_moderator' => false])->save();
            } else {
                $user->delete();
            }
        });

        return response()->json(null, 204);
    }

    /** @return Builder<User> */
    private function staff(): Builder
    {
        return User::query()->where(fn (Builder $q) => $q->where('is_super_admin', true)->orWhere('is_platform_moderator', true));
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($user->isPlatformStaff(), 404);
    }

    private function assignRole(User $user, PlatformRole $role): void
    {
        $user->forceFill([
            'is_super_admin' => $role === PlatformRole::SuperAdmin,
            'is_platform_moderator' => $role === PlatformRole::Moderator,
        ])->save();
    }
}
