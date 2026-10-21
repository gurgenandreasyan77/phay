<?php

namespace App\Google;

final readonly class GoogleToken
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
        public ?string $scope = null,
        /** The account's address (from the id token), when Google sends it. */
        public ?string $email = null,
    ) {}
}
