<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API (after auth:sanctum): the signed-in user's current business.
 * Runs after authentication, so it always has a user.
 */
class ResolveAdminTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()->resolveCurrentTenant();

        abort_unless($tenant, 403, 'Your account is not linked to any business.');

        // A suspended business can't be worked on by its own people; the
        // platform administrator still can (e.g. to look into it).
        abort_unless(
            $tenant->isActive() || $request->user()->is_super_admin,
            403,
            'This business is suspended.',
        );

        return $this->context->run($tenant, fn () => $next($request));
    }
}
