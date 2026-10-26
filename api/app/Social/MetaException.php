<?php

namespace App\Social;

use RuntimeException;

/** Meta answered with an error, or couldn't be reached. */
class MetaException extends RuntimeException
{
    public function __construct(string $message = '', public readonly ?int $metaCode = null, public readonly ?int $metaSubcode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
