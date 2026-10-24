<?php

namespace App\Security;

use App\Enums\AccountTokenPurpose;
use App\Models\AccountToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-time links: an invitation to set the first password, a password
 * reset. A new link of a purpose cancels the older ones; using one burns it.
 */
class AccountTokens
{
    /** @return array{token: string, url: string, expiresAt: Carbon} */
    public function issue(User $user, AccountTokenPurpose $purpose, ?User $createdBy = null, ?int $ttlMinutes = null): array
    {
        $this->revoke($user, $purpose);

        $token = Str::random(64);
        $expiresAt = now()->addMinutes($ttlMinutes ?? $purpose->ttlMinutes());

        AccountToken::query()->create([
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'token_hash' => self::hash($token),
            'expires_at' => $expiresAt,
            'created_by_id' => $createdBy?->getKey(),
        ]);

        return ['token' => $token, 'url' => self::url($purpose, $token), 'expiresAt' => $expiresAt];
    }

    /** The link's record while it can still be used, else null. */
    public function find(string $token, AccountTokenPurpose $purpose): ?AccountToken
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        return AccountToken::query()
            ->with('user')
            ->where('token_hash', self::hash($token))
            ->where('purpose', $purpose)
            ->usable()
            ->first();
    }

    public function consume(AccountToken $record): void
    {
        $record->forceFill(['used_at' => now()])->save();
        $this->revoke($record->user, $record->purpose);
    }

    public function revoke(User $user, AccountTokenPurpose $purpose): void
    {
        AccountToken::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    public function hasPending(User $user, AccountTokenPurpose $purpose): bool
    {
        return AccountToken::query()->where('user_id', $user->getKey())->where('purpose', $purpose)->usable()->exists();
    }

    public static function url(AccountTokenPurpose $purpose, string $token): string
    {
        return config('security.admin_url').$purpose->path().'#'.$token;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
