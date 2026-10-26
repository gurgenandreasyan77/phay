<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\Admin\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Security\AccessTokens;
use App\Security\LoginChallenges;
use App\Security\SecurityLog;
use App\Security\TwoFactor;
use App\Subscriptions\Subscriptions;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends Controller
{
    /** A hash of nothing in particular, checked against when the email is unknown. */
    private static ?string $dummyHash = null;

    /**
     * Step one: email and password. Without 2FA this signs in; with 2FA it
     * gives a challenge for login/two-factor instead of a session. A wrong
     * password, an unknown email and an invited login that has no password
     * yet all get the same answer.
     */
    public function login(LoginRequest $request, LoginChallenges $challenges, AccessTokens $tokens, SecurityLog $log): JsonResponse
    {
        $credentials = $request->validated();
        $email = Str::lower(trim($credentials['email']));
        $user = User::query()->where('email', $email)->first();

        // Hashes even for an unknown email, so timing doesn't tell which emails exist.
        $valid = Hash::check($credentials['password'], $user?->password ?? (self::$dummyHash ??= Hash::make(Str::random(32)))) && $user?->isActivated();

        if (! $valid) {
            $log->record(SecurityEvent::LoginFailed, $user, [], email: $email);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['data' => ['twoFactor' => true, ...$challenges->create($user)]]);
        }

        return $this->signedIn($user, $tokens->issue($user, $request));
    }

    /** Step two: the code from the authenticator app, or a recovery code. */
    public function twoFactor(Request $request, LoginChallenges $challenges, TwoFactor $twoFactor, AccessTokens $tokens, SecurityLog $log): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:16', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $challenges->user($validated['challenge']);

        if (! $user) {
            throw ValidationException::withMessages(['challenge' => ['Ժամկետն անցել է։ Մուտք գործեք նորից։']]);
        }

        if (! $twoFactor->verify($user, $validated['code'] ?? null, $validated['recovery_code'] ?? null)) {
            $log->record(SecurityEvent::TwoFactorFailed, $user);
            $left = $challenges->fail($validated['challenge']);

            throw ValidationException::withMessages($left
                ? ['code' => ['Կոդը սխալ է։']]
                : ['challenge' => ['Չափազանց շատ սխալ կոդեր։ Մուտք գործեք նորից։']]);
        }

        $challenges->forget($validated['challenge']);

        return $this->signedIn($user, $tokens->issue($user, $request, ['two_factor' => isset($validated['code']) ? 'app' : 'recovery_code']));
    }

    public function logout(Request $request, SecurityLog $log): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $log->record(SecurityEvent::Logout, $request->user());

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

        // Only into a business of one's own: platform staff looks at the
        // others from the platform level (GET /admin/tenants/{t}/overview).
        abort_unless($user->isMemberOf($tenant), 403, 'You have no access to this business.');

        $user->forceFill(['current_tenant_id' => $tenant->id])->save();
        $user->setRelation('currentTenant', $tenant);

        return response()->json(['data' => $this->presentMe($user, $tenant)]);
    }

    private function signedIn(User $user, NewAccessToken $token): JsonResponse
    {
        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isSuperAdmin' => $user->is_super_admin,
                    'platformRole' => $user->platformRole()?->value,
                    // Signed in, but has to set up 2FA before anything else.
                    'twoFactorSetupRequired' => $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled(),
                ],
            ],
        ]);
    }

    private function presentMe(User $user, Tenant $current): array
    {
        $role = $user->businessRole($current);

        // The businesses one can switch to: one's own (platform staff too).
        $tenants = $user->tenants()->orderBy('tenants.id')->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isSuperAdmin' => $user->is_super_admin,
            'platformRole' => $user->platformRole()?->value,
            // The role in the current business and what it allows there
            // (none for platform staff that isn't one of its people).
            'businessRole' => $role?->value,
            'permissions' => $role?->permissions() ?? [],
            'currentTenant' => new TenantResource($current),
            // The current business's subscription: a banner before it ends, a
            // lock screen after (never for platform staff).
            'subscription' => $this->subscriptionSummary($user, $current),
            'tenants' => TenantResource::collection($tenants),
            'security' => [
                'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
                'twoFactorRequired' => $required = $user->requiresTwoFactor(),
                'twoFactorSetupRequired' => $required && ! $user->hasTwoFactorEnabled(),
                'emailVerified' => $user->email_verified_at !== null,
                'phoneVerified' => $user->phone_verified_at !== null,
            ],
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
            // Not on premium: the header invites the owner to it.
            'premium' => $subscriptions->isPremium($subscription),
        ];
    }
}
