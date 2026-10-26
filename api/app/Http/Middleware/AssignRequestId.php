<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request gets an id: the one the admin's server (BFF) sends in
 * X-Request-Id, or a new one. It goes back in the same header, into every
 * log line of the request (Context) and into error answers, so an error a
 * person sees in the admin can be found in the API log.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::for($request);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    /** The request's id, assigned on first use (an error may come before this middleware ran). */
    public static function for(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        if (! is_string($id)) {
            $sent = $request->headers->get(self::HEADER);
            // Only a plain token: it ends up in logs and headers.
            $id = is_string($sent) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $sent) ? $sent : (string) Str::uuid();

            $request->attributes->set(self::ATTRIBUTE, $id);
            Context::add(self::ATTRIBUTE, $id);
        }

        return $id;
    }
}
