<?php

namespace App\Exceptions;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every error of the API has the same JSON shape, so the admin can show it
 * and a developer can find it:
 *
 *     {
 *       "message": "Your role in this business does not allow this.",
 *       "code": "forbidden",            // stable, for the client to branch on
 *       "status": 403,
 *       "request_id": "…",              // also in X-Request-Id and the log
 *       "errors": {…},                  // 422 only: field => messages
 *       "retry_after": 60,              // 429 only: seconds
 *       "debug": {…}                    // APP_DEBUG only: class, file, line, trace
 *     }
 *
 * `message` and `errors` stay where Laravel puts them, so forms keep
 * working. Outside debug a 500 says only "Server Error": the details are
 * in the log, under the same request id.
 */
class ApiExceptionRenderer
{
    /** Codes for statuses that have no more specific one. */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        402 => 'payment_required',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        410 => 'gone',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    public function __construct(private readonly bool $debug) {}

    public static function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        // Answers that already are a response (e.g. abort(response(...))) stay as they are.
        if ($e instanceof HttpResponseException || ! self::wantsJson($request)) {
            return null;
        }

        $e = $this->cause($e);
        [$status, $code, $message, $extra, $headers] = $this->describe($e);

        $body = [
            'message' => $message,
            'code' => $code,
            'status' => $status,
            'request_id' => AssignRequestId::for($request),
            ...$extra,
        ];

        if ($this->debug) {
            $body['debug'] = $this->debugInfo($e);
        }

        return new JsonResponse($body, $status, [
            ...$headers,
            AssignRequestId::HEADER => $body['request_id'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * Laravel turns some exceptions into HTTP ones before they get here (a
     * missing model into a 404, a denied policy into a 403); the original
     * says more, so it is the one described.
     */
    private function cause(Throwable $e): Throwable
    {
        $previous = $e->getPrevious();

        return $e instanceof HttpExceptionInterface && (
            $previous instanceof ModelNotFoundException
            || $previous instanceof ItemNotFoundException
            || $previous instanceof RecordsNotFoundException
            || $previous instanceof AuthorizationException
            || $previous instanceof TokenMismatchException
        ) ? $previous : $e;
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>, 4: array<string, string>}
     */
    private function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                $e->status, self::code($e->status), $e->getMessage(), ['errors' => $e->errors()], [],
            ],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.', [], []],
            $e instanceof AuthorizationException => [
                $status = $e->status() ?? 403, self::code($status), $e->getMessage() ?: 'This action is unauthorized.', [], [],
            ],
            // The model and id are for the developer (debug), not for the screen.
            $e instanceof ModelNotFoundException, $e instanceof ItemNotFoundException, $e instanceof RecordsNotFoundException => [404, 'not_found', 'Not found.', [], []],
            $e instanceof TokenMismatchException => [419, 'session_expired', 'The session has expired. Reload the page.', [], []],
            $e instanceof ThrottleRequestsException => [
                429,
                'too_many_requests',
                $e->getMessage() ?: 'Too many requests. Try again later.',
                isset($e->getHeaders()['Retry-After']) ? ['retry_after' => (int) $e->getHeaders()['Retry-After']] : [],
                $e->getHeaders(),
            ],
            $e instanceof ApiException => [$e->getStatusCode(), $e->errorCode, $this->httpMessage($e), [], $e->getHeaders()],
            $e instanceof HttpExceptionInterface => [
                $status = $e->getStatusCode(),
                $status === 503 && app()->isDownForMaintenance() ? 'maintenance' : self::code($status),
                $this->httpMessage($e),
                [],
                $e->getHeaders(),
            ],
            default => [500, 'server_error', $this->debug ? ($e->getMessage() ?: 'Server Error') : 'Server Error', [], []],
        };
    }

    private function httpMessage(HttpExceptionInterface $e): string
    {
        $message = $e instanceof Throwable ? $e->getMessage() : '';

        // A missing route says which; that is only useful while developing.
        if ($message === '' || (! $this->debug && str_starts_with($message, 'The route '))) {
            return Response::$statusTexts[$e->getStatusCode()] ?? 'Error';
        }

        return $message;
    }

    private static function code(int $status): string
    {
        return self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'error');
    }

    /** @return array<string, mixed> */
    private function debugInfo(Throwable $e): array
    {
        $info = [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->take(30)->map(fn (array $frame) => Arr::except($frame, ['args']))->all(),
        ];

        if ($previous = $e->getPrevious()) {
            $info['previous'] = [
                'exception' => $previous::class,
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
        }

        return $info;
    }
}
