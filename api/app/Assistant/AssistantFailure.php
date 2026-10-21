<?php

namespace App\Assistant;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;

/**
 * Why a call to the model failed, in words the admin can act on: a wrong
 * key, no credit left, a model the account can't use, the network... The
 * API's own message goes along as `detail` (it never contains the key).
 */
final readonly class AssistantFailure
{
    private function __construct(
        public string $code,
        public string $message,
        public int $status,
        public ?string $detail,
    ) {}

    public static function from(APIException $e): self
    {
        if ($e instanceof APIConnectionException) {
            return new self('network', 'The server could not reach the Anthropic API (network or timeout).', 504, $e->getMessage());
        }

        $status = $e instanceof APIStatusException ? $e->status : null;
        $detail = self::apiMessage($e);
        $lower = mb_strtolower((string) $detail);

        return match (true) {
            $status === 401 => new self('invalid_key', 'ANTHROPIC_API_KEY is wrong or revoked.', 502, $detail),
            str_contains($lower, 'credit balance') || str_contains($lower, 'billing') => new self('no_credit', 'The Anthropic account has no credit left.', 502, $detail),
            $status === 403 => new self('forbidden', 'The Anthropic account may not use this API or model.', 502, $detail),
            $status === 404 => new self('bad_model', 'The model in ASSISTANT_MODEL is not available to this account.', 502, $detail),
            $status === 429 => new self('rate_limited', 'Too many requests or the spend limit was reached on the Anthropic account.', 503, $detail),
            $status !== null && $status >= 500 => new self('unavailable', 'The AI assistant is unavailable right now. Try again in a minute.', 503, $detail),
            default => new self('bad_request', 'The Anthropic API rejected the request.', 502, $detail),
        };
    }

    private static function apiMessage(APIException $e): ?string
    {
        if ($e instanceof APIStatusException) {
            $body = $e->body;
            if (is_array($body) && is_string($body['error']['message'] ?? null)) {
                return $body['error']['message'];
            }
            if (is_string($body) && $body !== '') {
                return mb_substr($body, 0, 500);
            }
        }

        return mb_substr($e->getMessage(), 0, 500);
    }
}
