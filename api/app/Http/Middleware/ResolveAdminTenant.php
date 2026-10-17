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

        return $this->context->run($tenant, fn () => $next($request));
    }
}
