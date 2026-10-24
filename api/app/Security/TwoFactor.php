<?php

namespace App\Security;

use App\Enums\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-factor authentication with an authenticator app (TOTP) and one-time
 * recovery codes for a lost phone.
 *
 * Turning it on is two steps: begin() stores a new secret (not active yet)
 * and gives what the QR code shows; confirm() checks a first code from the
 * app, activates it and hands out the recovery codes, the only time they
 * are shown.
 */
class TwoFactor
{
    public function __construct(private readonly SecurityLog $log) {}

    /** @return array{secret: string, otpauthUrl: string} */
    public function begin(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => ['2FA-ն արդեն միացված է։']]);
        }

        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();

        return ['secret' => $secret, 'otpauthUrl' => $this->uri($user, $secret)];
    }

    /** @return list<string> the recovery codes, in plain text */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret) {
            throw ValidationException::withMessages(['code' => ['Սկզբից սեղմեք «Միացնել 2FA»։']]);
        }

        $step = Totp::verify($user->two_factor_secret, $code);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => ['Կոդը սխալ է կամ ժամկետանց։ Ստուգեք հեռախոսի ժամը։']]);
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => array_map(self::hash(...), $codes),
        ])->save();

        $this->log->record(SecurityEvent::TwoFactorEnabled, $user);

        return $codes;
    }

    /**
     * Checks a sign-in code: one from the app, or else a recovery code (which
     * is then used up). An app code works once.
     */
    public function verify(User $user, ?string $code, ?string $recoveryCode = null): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        if ($code !== null && $code !== '') {
            return DB::transaction(function () use ($user, $code) {
                $fresh = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $step = Totp::verify($fresh->two_factor_secret, $code, $fresh->two_factor_last_step);

                if ($step === null) {
                    return false;
                }

                $fresh->forceFill(['two_factor_last_step' => $step])->save();
                $user->two_factor_last_step = $step;

                return true;
            });
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $hash = self::hash($recoveryCode);
            $remaining = $user->two_factor_recovery_codes ?? [];
            $index = array_search($hash, $remaining, true);

            if ($index === false) {
                return false;
            }

            unset($remaining[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();
            $this->log->record(SecurityEvent::RecoveryCodeUsed, $user, ['left' => count($remaining)]);

            return true;
        }

        return false;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(self::hash(...), $codes)])->save();
        $this->log->record(SecurityEvent::RecoveryCodesRegenerated, $user);

        return $codes;
    }

    /**
     * Turns 2FA off: by the person themselves, or (lost phone and recovery
     * codes) reset by staff or from the console; then they set it up again
     * at the next sign-in if it's required. Every session is signed out.
     */
    public function disable(User $user, ?User $actor = null, string $via = 'self'): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        if ($via === 'self') {
            $this->log->record(SecurityEvent::TwoFactorDisabled, $user);
        } else {
            $user->tokens()->delete();
            $this->log->record(SecurityEvent::TwoFactorReset, $user, ['via' => $via], $actor);
        }
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    private function uri(User $user, string $secret): string
    {
        return Totp::uri($secret, $user->email, (string) config('security.two_factor.issuer'));
    }

    /** @return list<string> e.g. "k7m2-x9qa" */
    private function newRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes = [];

        for ($i = 0; $i < (int) config('security.two_factor.recovery_codes'); $i++) {
            $raw = '';
            for ($j = 0; $j < 8; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 4).'-'.substr($raw, 4);
        }

        return $codes;
    }

    /** Case, spaces and the dash don't matter when typed back. */
    private static function hash(string $code): string
    {
        return hash('sha256', Str::lower(preg_replace('/[\s-]+/', '', $code) ?? ''));
    }
}
