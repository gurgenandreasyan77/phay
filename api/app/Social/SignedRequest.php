<?php

namespace App\Social;

/**
 * Meta's signed_request (deauthorize and data deletion callbacks):
 * "signature.payload", both base64url, the signature an HMAC-SHA256 of the
 * payload with the app secret. Returns the payload only when the signature
 * is right.
 */
final class SignedRequest
{
    /** @return array<string, mixed>|null */
    public static function parse(?string $signedRequest, ?string $secret): ?array
    {
        if (! is_string($signedRequest) || blank($secret) || substr_count($signedRequest, '.') !== 1) {
            return null;
        }

        [$signature, $payload] = explode('.', $signedRequest, 2);
        $expected = hash_hmac('sha256', $payload, $secret, true);

        if (! hash_equals($expected, self::decode($signature))) {
            return null;
        }

        $data = json_decode(self::decode($payload), true);

        return is_array($data) && strtoupper((string) ($data['algorithm'] ?? '')) === 'HMAC-SHA256' ? $data : null;
    }

    /** Builds one, as Meta would (tests). */
    public static function make(array $data, string $secret): string
    {
        $payload = self::encode(json_encode(['algorithm' => 'HMAC-SHA256', ...$data]));

        return self::encode(hash_hmac('sha256', $payload, $secret, true)).'.'.$payload;
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), false);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
