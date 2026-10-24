<?php

namespace App\Security;

use App\Enums\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Admin panel sessions (Sanctum tokens kept in the admin's httpOnly
 * cookie). Each remembers where it was signed in from, so the person can
 * see their devices and sign one out. Tokens expire after
 * sanctum.expiration minutes.
 */
class AccessTokens
{
    public const NAME = 'admin-panel';

    public function __construct(private readonly SecurityLog $log) {}

    /** @param array<string, mixed> $meta */
    public function issue(User $user, Request $request, array $meta = []): NewAccessToken
    {
        $token = $user->createToken(self::NAME);
        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ])->save();

        $this->log->record(SecurityEvent::LoginSucceeded, $user, $meta);

        return $token;
    }

    /** Signs out every other device; the current one (if any) stays in. */
    public function revokeOthers(User $user): int
    {
        $current = $user->currentAccessToken();
        $id = $current && method_exists($current, 'getKey') ? $current->getKey() : null;

        return $user->tokens()->when($id, fn ($query) => $query->whereKeyNot($id))->delete();
    }
}
