<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * An HTTP error with its own stable code, for the admin to branch on
 * (abort() only takes a status and a message):
 *
 *     throw new ApiException(403, 'business_suspended', 'This business is suspended.');
 *
 * Rendered by ApiExceptionRenderer like any other error.
 */
class ApiException extends HttpException
{
    /** @param  array<string, string>  $headers */
    public function __construct(
        int $status,
        public readonly string $errorCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($status, $message, $previous, $headers);
    }
}
