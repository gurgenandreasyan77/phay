<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A section of the business admin (business.can:catalog, ...): only for
 * roles that have it in the current business, see App\Enums\BusinessRole.
 * Runs after tenant.admin, so the business is already resolved.
 */
class EnsureBusinessPermission
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $role = $request->user()->businessRole($this->context->current());

        abort_unless($role?->can($permission), 403, 'Your role in this business does not allow this.');

        return $next($request);
    }
}
