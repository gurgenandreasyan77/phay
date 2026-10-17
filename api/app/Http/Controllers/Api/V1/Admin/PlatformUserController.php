<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlatformUserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Servio's own team on the platform (super admin only): other super admins
 * and moderators who onboard clients. Like business logins, passwords are
 * stored hashed; a forgotten one is replaced with a new one.
 */
class PlatformUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlatformUserResource::collection($this->staff()->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', 'string', Rule::enum(PlatformRole::class)],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $this->assignRole($user, PlatformRole::from($validated['role']));

            return $user;
        });

        return (new PlatformUserResource($user))->response()->setStatusCode(201);
    }

    /** Renames, changes the role, or sets a new password (signs out everywhere). */
    public function update(Request $request, User $user): PlatformUserResource
    {
        $this->ensureStaff($user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', Password::min(8)],
            'role' => ['sometimes', 'required', 'string', Rule::enum(PlatformRole::class)],
        ]);

        if (isset($validated['role']) && $user->is($request->user()) && $validated['role'] !== PlatformRole::SuperAdmin->value) {
            throw ValidationException::withMessages(['role' => ['Չեք կարող փոխել ձեր սեփական դերը։']]);
        }

        DB::transaction(function () use ($user, $validated) {
            $user->fill(array_intersect_key($validated, array_flip(['name', 'password'])))->save();

            if (isset($validated['role'])) {
                $this->assignRole($user, PlatformRole::from($validated['role']));
            }

            if (array_key_exists('password', $validated)) {
                $user->tokens()->delete();
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
