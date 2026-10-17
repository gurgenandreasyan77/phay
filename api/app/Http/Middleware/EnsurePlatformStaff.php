<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding screens (businesses, their sites and logins): the super admin
 * and platform moderators. Turning modules on/off and the platform team stay
 * with the super admin (EnsureSuperAdmin).
 */
class EnsurePlatformStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403, 'Only platform staff can do this.');

        return $next($request);
    }
}
