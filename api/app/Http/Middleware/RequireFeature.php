<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group behind a feature flag of the current tenant (Pennant's
 * default scope, see AppServiceProvider). When the flag is off, the route
 * behaves as if it doesn't exist. Several flags mean any of them opens it.
 *
 *     Route::middleware('feature:auto-care')->group(function () {
 *         // ...
 *     });
 */
class RequireFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        abort_unless(Feature::someAreActive($features), 404);

        return $next($request);
    }
}
