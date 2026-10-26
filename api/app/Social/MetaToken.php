<?php

namespace App\Social;

final readonly class MetaToken
{
    public function __construct(
        public string $accessToken,
        /** Seconds the token lasts; null when Meta doesn't say (a token that doesn't expire). */
        public ?int $expiresIn,
    ) {}
}
