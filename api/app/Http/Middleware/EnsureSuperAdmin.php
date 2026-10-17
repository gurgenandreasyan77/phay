<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform screens (businesses, their logins, sites, feature flags): only a
 * super admin. A business owner signs in to the same admin panel but works
 * on their own business only.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Only a platform administrator can do this.');

        return $next($request);
    }
}
