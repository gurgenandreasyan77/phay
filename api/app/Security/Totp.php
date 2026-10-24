<?php

namespace App\Security;

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226): the 6-digit codes
 * of Google Authenticator, Microsoft Authenticator, 1Password and the like.
 * SHA-1, 30-second steps, one step of clock drift either way.
 */
class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    private const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 160 random bits, base32 as the apps expect. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * The step the code belongs to, or null when it's wrong. A step at or
     * before $afterStep is refused, so an accepted code can't be used again.
     */
    public static function verify(string $secret, string $code, ?int $afterStep = null, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $key = self::base32Decode($secret);
        $current = intdiv($now ?? time(), self::PERIOD);

        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if ($afterStep !== null && $step <= $afterStep) {
                continue;
            }

            if (hash_equals(self::at($key, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** The code for a time (tests, and RFC test vectors). */
    public static function code(string $secret, ?int $now = null): string
    {
        return self::at(self::base32Decode($secret), intdiv($now ?? time(), self::PERIOD));
    }

    /** What the QR code holds: otpauth://totp/Issuer:account?secret=... */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function at(string $key, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]+/', '', $text) ?? '');
        $bits = '';
        foreach (str_split($text) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) {
                continue;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
