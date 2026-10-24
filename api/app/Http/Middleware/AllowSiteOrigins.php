<?php

namespace App\Http\Middleware;

use App\Sites\SiteOrigins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the API: the origins from env (the admin app, local dev) plus the
 * origins of every active site, so connecting a new site needs no deploy.
 * Runs before Laravel's HandleCors, which reads config('cors') per request.
 */
class AllowSiteOrigins
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && $request->headers->has('Origin')) {
            config(['cors.allowed_origins' => array_values(array_unique([
                ...config('cors.env_allowed_origins', []),
                ...SiteOrigins::all(),
            ]))]);
        }

        return $next($request);
    }
}
