<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The second step of a sign-in with 2FA: after the right password the
 * browser gets a short-lived challenge (not a session) and trades it, with a
 * code, for the session. A few wrong codes burn the challenge, so guessing
 * means typing the password again.
 */
class LoginChallenges
{
    /** @return array{challenge: string, expiresIn: int} */
    public function create(User $user): array
    {
        $challenge = Str::random(64);
        $ttl = (int) config('security.two_factor.challenge_ttl_minutes') * 60;

        Cache::put($this->key($challenge), ['user_id' => $user->getKey(), 'attempts' => 0], $ttl);

        return ['challenge' => $challenge, 'expiresIn' => $ttl];
    }

    public function user(string $challenge): ?User
    {
        $data = Cache::get($this->key($challenge));

        return $data ? User::query()->find($data['user_id']) : null;
    }

    /** Counts a wrong code; true while attempts are left. */
    public function fail(string $challenge): bool
    {
        $key = $this->key($challenge);
        $data = Cache::get($key);

        if (! $data) {
            return false;
        }

        $data['attempts']++;

        if ($data['attempts'] >= (int) config('security.two_factor.challenge_attempts')) {
            Cache::forget($key);

            return false;
        }

        Cache::put($key, $data, (int) config('security.two_factor.challenge_ttl_minutes') * 60);

        return true;
    }

    public function forget(string $challenge): void
    {
        Cache::forget($this->key($challenge));
    }

    private function key(string $challenge): string
    {
        return 'login-challenge:'.hash('sha256', $challenge);
    }
}
