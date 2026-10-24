<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountTokenPurpose;
use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Models\AccountToken;
use App\Models\User;
use App\Security\AccessTokens;
use App\Security\AccountLinks;
use App\Security\AccountTokens;
use App\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Pages opened from a one-time link, before signing in:
 *
 * - an invitation: the new login sets its first password and is signed in
 *   (then sets up 2FA if it must);
 * - "forgot password": always the same answer, so it doesn't tell which
 *   emails have a login; the link goes by email only;
 * - a password reset: a new password, every session signed out, and 2FA
 *   still asked at the next sign-in.
 */
class AccountLinkController extends Controller
{
    public function __construct(
        private readonly AccountTokens $tokens,
        private readonly SecurityLog $log,
    ) {}

    public function showInvitation(Request $request): JsonResponse
    {
        $record = $this->invitation($request);
        $user = $record->user;

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => $user->email,
            'businesses' => $user->tenants()->pluck('name'),
            'platformRole' => $user->platformRole()?->value,
            'twoFactorRequired' => $user->requiresTwoFactor(),
            'expiresAt' => $record->expires_at->toIso8601String(),
        ]]);
    }

    public function acceptInvitation(Request $request, AccessTokens $accessTokens): JsonResponse
    {
        $record = $this->invitation($request);
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($record, $validated) {
            $user = $record->user;
            $user->forceFill(['password' => $validated['password'], 'password_changed_at' => now()])->save();
            $this->tokens->consume($record);

            return $user;
        });

        $this->log->record(SecurityEvent::InvitationAccepted, $user);
        $token = $accessTokens->issue($user, $request, ['via' => 'invitation']);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isSuperAdmin' => $user->is_super_admin,
                'platformRole' => $user->platformRole()?->value,
                'twoFactorSetupRequired' => $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled(),
            ],
        ]]);
    }

    public function forgot(Request $request, AccountLinks $links): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $user = User::query()->where('email', Str::lower(trim($validated['email'])))->first();

        // Only someone who already has a password; an invited login uses its invitation.
        if ($user?->isActivated()) {
            $links->passwordReset($user);
        }

        return response()->json(['data' => ['status' => 'sent']]);
    }

    public function showReset(Request $request): JsonResponse
    {
        $record = $this->passwordReset($request);

        return response()->json(['data' => [
            'email' => $record->user->email,
            'expiresAt' => $record->expires_at->toIso8601String(),
        ]]);
    }

    public function reset(Request $request): JsonResponse
    {
        $record = $this->passwordReset($request);
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($record, $validated) {
            $user = $record->user;
            $user->forceFill(['password' => $validated['password'], 'password_changed_at' => now()])->save();
            $user->tokens()->delete();
            $this->tokens->consume($record);
        });

        $this->log->record(SecurityEvent::PasswordReset, $record->user);

        return response()->json(['data' => ['status' => 'ok']]);
    }

    private function invitation(Request $request): AccountToken
    {
        return $this->tokens->find((string) $request->input('token', ''), AccountTokenPurpose::Invitation)
            ?? throw $this->invalidLink();
    }

    /** The token comes in the body, never in a URL that a server could log. */
    private function passwordReset(Request $request): AccountToken
    {
        return $this->tokens->find((string) $request->input('token', ''), AccountTokenPurpose::PasswordReset)
            ?? throw $this->invalidLink();
    }

    private function invalidLink(): ValidationException
    {
        return ValidationException::withMessages([
            'token' => ['Հղումն անվավեր է կամ ժամկետանց։ Խնդրեք նորը։'],
        ]);
    }
}
