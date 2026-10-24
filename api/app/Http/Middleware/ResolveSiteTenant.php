<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API: the X-Site-Key header picks the tenant whose content is
 * served and who receives the leads. Every site must send it; there is no
 * fallback business, so a misconfigured site fails loudly instead of
 * showing someone else's content.
 */
class ResolveSiteTenant
{
    public const HEADER = 'X-Site-Key';

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if ($key === null || $key === '') {
            abort(401, 'Site key required.');
        }

        $site = Site::query()->with('tenant')->where('public_key', $key)->first();

        if (! $site || ! $site->is_active) {
            abort(401, 'Invalid site key.');
        }

        if (! $site->allowsOrigin($request->header('Origin'))) {
            abort(403, 'Origin not allowed for this site.');
        }

        return $this->runAs($site->tenant, $request, $next);
    }

    private function runAs($tenant, Request $request, Closure $next): Response
    {
        abort_unless($tenant->isActive(), 403, 'This business is suspended.');

        return $this->context->run($tenant, fn () => $next($request));
    }
}
