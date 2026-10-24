<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessRole;
use App\Enums\SecurityEvent;
use App\Enums\VerificationPurpose;
use App\Http\Controllers\Controller;
use App\Models\SecurityLogEntry;
use App\Models\User;
use App\Security\AccessTokens;
use App\Security\PhoneNumber;
use App\Security\SecurityLog;
use App\Security\TwoFactor;
use App\Security\VerificationCodes;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The signed-in person's own account security: 2FA, recovery codes, a
 * confirmed email and phone, their signed-in devices and recent events.
 * Turning 2FA on or off and new recovery codes ask for the password again.
 */
class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactor $twoFactor,
        private readonly AccessTokens $tokens,
        private readonly SecurityLog $log,
    ) {}

    public function show(Request $request, VerificationCodes $codes, TenantContext $context): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $tenant = $context->current();

        return response()->json(['data' => [
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'required' => $user->requiresTwoFactor(),
                'confirmedAt' => $user->two_factor_confirmed_at?->toIso8601String(),
                'recoveryCodesLeft' => $this->twoFactor->remainingRecoveryCodes($user),
            ],
            'email' => [
                'address' => $user->email,
                'verifiedAt' => $user->email_verified_at?->toIso8601String(),
            ],
            'phone' => [
                'number' => $user->phone,
                'verifiedAt' => $user->phone_verified_at?->toIso8601String(),
                // A new number waiting for its SMS code.
                'pending' => PhoneNumber::mask($codes->pendingDestination($user, VerificationPurpose::Phone)),
            ],
            'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
            'sessions' => $user->tokens()->latest('last_used_at')->latest('id')->limit(30)->get()
                ->map(fn (PersonalAccessToken $token) => [
                    'id' => $token->id,
                    'ipAddress' => $token->ip_address,
                    'userAgent' => $token->user_agent,
                    'createdAt' => $token->created_at?->toIso8601String(),
                    'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                    'current' => $current instanceof PersonalAccessToken && $current->is($token),
                ]),
            'events' => SecurityLogEntry::query()->where('user_id', $user->id)->latest('id')->limit(20)->get()
                ->map(fn (SecurityLogEntry $entry) => SecurityEventController::present($entry)),
            // The business's own rule, for its owner to change.
            'business' => $tenant && $user->businessRole($tenant) === BusinessRole::Owner ? [
                'name' => $tenant->name,
                'requireTwoFactor' => (bool) $tenant->require_two_factor,
            ] : null,
        ]]);
    }

    /** Starts turning 2FA on: a new secret for the QR code. */
    public function beginTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->confirmPassword($request, $user);

        return response()->json(['data' => $this->twoFactor->begin($user)]);
    }

    /** The first code from the app turns 2FA on; every other device is signed out. */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $request->user();

        $codes = $this->twoFactor->confirm($user, $validated['code']);
        $this->tokens->revokeOthers($user);

        return response()->json(['data' => ['recoveryCodes' => $codes]]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->requiresTwoFactor()) {
            throw ValidationException::withMessages(['code' => ['Ձեր հաշվի համար 2FA-ն պարտադիր է, այն չի կարելի անջատել։']]);
        }

        $this->confirmPassword($request, $user);
        $this->confirmCode($request, $user);
        $this->twoFactor->disable($user);

        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => ['2FA-ն միացված չէ։']]);
        }

        $this->confirmPassword($request, $user);

        return response()->json(['data' => ['recoveryCodes' => $this->twoFactor->regenerateRecoveryCodes($user)]]);
    }

    public function sendEmailCode(Request $request, VerificationCodes $codes): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            throw ValidationException::withMessages(['code' => ['Էլ. փոստն արդեն հաստատված է։']]);
        }

        $codes->send($user, VerificationPurpose::Email, $user->email);

        return response()->json(['data' => ['status' => 'sent']]);
    }

    public function verifyEmail(Request $request, VerificationCodes $codes): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $request->user();

        $destination = $codes->verify($user, VerificationPurpose::Email, $validated['code']);

        // The code went to the address the login had then; it must still be the same.
        if ($destination === $user->email) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->log->record(SecurityEvent::EmailVerified, $user);
        }

        return response()->json(['data' => ['status' => 'ok']]);
    }

    /** A new phone number gets an SMS code; it is saved once the code comes back. */
    public function sendPhoneCode(Request $request, VerificationCodes $codes): JsonResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $phone = PhoneNumber::normalize($validated['phone']);

        if (! $phone) {
            throw ValidationException::withMessages(['phone' => ['Հեռախոսահամարը սխալ է։ Օրինակ՝ 055 12 34 56 կամ +374 55 123456։']]);
        }

        $codes->send($request->user(), VerificationPurpose::Phone, $phone);

        return response()->json(['data' => ['status' => 'sent', 'phone' => PhoneNumber::mask($phone)]]);
    }

    public function verifyPhone(Request $request, VerificationCodes $codes): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $request->user();

        $phone = $codes->verify($user, VerificationPurpose::Phone, $validated['code']);
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        $this->log->record(SecurityEvent::PhoneVerified, $user, ['phone' => PhoneNumber::mask($phone)]);

        return response()->json(['data' => ['status' => 'ok', 'phone' => $phone]]);
    }

    /** Signs one of the person's own devices out. */
    public function revokeSession(Request $request, int $session): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->whereKey($session)->firstOrFail();
        $token->delete();
        $this->log->record(SecurityEvent::SessionRevoked, $user, ['session' => $session]);

        return response()->json(null, 204);
    }

    /** Signs every other device out. */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->tokens->revokeOthers($user);
        $this->log->record(SecurityEvent::SessionRevoked, $user, ['others' => $count]);

        return response()->json(['data' => ['revoked' => $count]]);
    }

    /** The owner asks everyone in their business to use 2FA. */
    public function updateBusiness(Request $request, TenantContext $context): JsonResponse
    {
        $validated = $request->validate(['require_two_factor' => ['required', 'boolean']]);
        $user = $request->user();
        $tenant = $context->current();

        abort_unless($tenant && $user->businessRole($tenant) === BusinessRole::Owner, 403, 'Only the owner can change this.');

        $tenant->forceFill(['require_two_factor' => $validated['require_two_factor']])->save();
        $this->log->record(SecurityEvent::BusinessTwoFactorPolicyChanged, $user, [
            'tenant_id' => $tenant->id,
            'require_two_factor' => $validated['require_two_factor'],
        ]);

        return response()->json(['data' => ['requireTwoFactor' => (bool) $tenant->require_two_factor]]);
    }

    private function confirmPassword(Request $request, User $user): void
    {
        $password = (string) $request->input('password', '');

        if ($password === '' || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => ['Գաղտնաբառը սխալ է։']]);
        }
    }

    private function confirmCode(Request $request, User $user): void
    {
        if (! $this->twoFactor->verify($user, (string) $request->input('code', ''))) {
            throw ValidationException::withMessages(['code' => ['Կոդը սխալ է։']]);
        }
    }
}
