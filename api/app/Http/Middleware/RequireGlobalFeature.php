<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group behind a global (app-wide) Pennant feature flag.
 * When the flag is off, the route behaves as if it doesn't exist.
 *
 *     Route::middleware('feature.global:auto-care')->group(function () {
 *         // ...
 *     });
 */
class RequireGlobalFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Feature::globally()->active($feature), 404);

        return $next($request);
    }
}
