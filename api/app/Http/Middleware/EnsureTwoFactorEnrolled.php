<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone who must use 2FA (see User::requiresTwoFactor) and hasn't set it
 * up yet can only set it up: every other admin call answers 403
 * two_factor_setup_required, whatever the token. Also covers sessions
 * signed in before 2FA became required.
 */
class EnsureTwoFactorEnrolled
{
    /** Who they are, signing out and the 2FA setup itself. */
    private const OPEN_BEFORE_SETUP = [
        'api/v1/admin/me',
        'api/v1/admin/logout',
        'api/v1/admin/security',
        'api/v1/admin/security/two-factor',
        'api/v1/admin/security/two-factor/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasTwoFactorEnabled() && ! $request->is(...self::OPEN_BEFORE_SETUP) && $user->requiresTwoFactor()) {
            return response()->json([
                'message' => 'Set up two-factor authentication first.',
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
