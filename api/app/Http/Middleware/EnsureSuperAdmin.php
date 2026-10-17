<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-wide switches (modules of a business, feature flags) and the
 * platform team: only the super admin. Onboarding screens are open to
 * platform moderators too (EnsurePlatformStaff).
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Only a platform administrator can do this.');

        return $next($request);
    }
}
