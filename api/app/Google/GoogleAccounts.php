<?php

namespace App\Google;

use App\Models\GoogleCalendar;
use App\Models\GoogleConnection;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Connecting a person's Google account to the current business: the OAuth
 * state, the tokens (fresh when used) and the list of the account's
 * calendars. Each person connects their own account; one per business.
 */
class GoogleAccounts
{
    /** How long the consent page may take. */
    private const STATE_MINUTES = 15;

    public function __construct(
        private readonly GoogleCalendarClient $google,
        private readonly TenantContext $tenants,
    ) {}

    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    public function connection(User $user): ?GoogleConnection
    {
        return GoogleConnection::query()->where('user_id', $user->id)->first();
    }

    /** Where to send the person: Google's consent page, with a state only they can bring back. */
    public function authUrl(User $user): string
    {
        return $this->google->authUrl(Crypt::encryptString(json_encode([
            'u' => $user->id,
            't' => $this->tenants->id(),
            'e' => now()->addMinutes(self::STATE_MINUTES)->timestamp,
            'n' => Str::random(16),
        ])));
    }

    /**
     * Back from Google: the state must be this person's, for this business,
     * and recent; the code becomes the tokens, the account's calendars are read.
     *
     * @throws InvalidGoogleState
     * @throws GoogleException
     */
    public function connect(User $user, string $code, string $state): GoogleConnection
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            throw new InvalidGoogleState;
        }

        if (! is_array($payload)
            || ($payload['u'] ?? null) !== $user->id
            || ($payload['t'] ?? null) !== $this->tenants->id()
            || ($payload['e'] ?? 0) < now()->timestamp) {
            throw new InvalidGoogleState;
        }

        $token = $this->google->exchangeCode($code);
        $connection = $this->connection($user);

        // Another account than before: its calendars and their events go.
        if ($connection !== null && $token->email !== null && $connection->email !== null && $connection->email !== $token->email) {
            $connection->calendars()->each(fn (GoogleCalendar $calendar) => $calendar->delete());
        }

        $connection ??= new GoogleConnection(['user_id' => $user->id]);
        $connection->fill([
            'email' => $token->email ?? $connection->email,
            'access_token' => $token->accessToken,
            // Google sends a refresh token only with consent; keep the old one otherwise.
            'refresh_token' => $token->refreshToken ?? $connection->refresh_token,
            'expires_at' => now()->addSeconds($token->expiresIn),
            'scopes' => $token->scope,
            'revoked_at' => null,
        ])->save();

        $this->refreshCalendars($connection);

        return $connection;
    }

    /**
     * An access token that works now: refreshed shortly before it expires.
     *
     * @throws GoogleAuthException when Google refuses (the connection is marked revoked)
     */
    public function accessToken(GoogleConnection $connection, bool $force = false): string
    {
        if ($connection->revoked_at !== null) {
            throw new GoogleAuthException('The Google access was revoked.');
        }

        if (! $force && $connection->expires_at !== null && $connection->expires_at->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            $connection->forceFill(['revoked_at' => now()])->save();
            throw new GoogleAuthException('No refresh token.');
        }

        try {
            $token = $this->google->refresh($connection->refresh_token);
        } catch (GoogleAuthException $e) {
            $connection->forceFill(['revoked_at' => now()])->save();
            throw $e;
        }

        $connection->forceFill([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?? $connection->refresh_token,
            'expires_at' => now()->addSeconds($token->expiresIn),
        ])->save();

        return $token->accessToken;
    }

    /**
     * Calls Google with a fresh token; once more with a refreshed one when
     * Google says the token is no longer good.
     *
     * @template T
     *
     * @param  callable(string): T  $call
     * @return T
     */
    public function withToken(GoogleConnection $connection, callable $call): mixed
    {
        try {
            return $call($this->accessToken($connection));
        } catch (GoogleAuthException $e) {
            if ($connection->revoked_at !== null) {
                throw $e;
            }

            return $call($this->accessToken($connection, force: true));
        }
    }

    /**
     * The account's calendars as Google lists them now: new ones added (not
     * chosen), renamed ones updated, removed ones deleted with their events.
     * Calendars showing only free/busy can't be read and are left out.
     */
    public function refreshCalendars(GoogleConnection $connection): void
    {
        $items = collect($this->withToken($connection, fn (string $token) => $this->google->calendars($token)))
            ->filter(fn (array $item) => filled($item['id'] ?? null) && ($item['accessRole'] ?? 'reader') !== 'freeBusyReader')
            ->keyBy('id');

        $known = $connection->calendars()->get()->keyBy('calendar_id');

        foreach ($items as $id => $item) {
            $calendar = $known->get($id) ?? new GoogleCalendar(['google_connection_id' => $connection->id, 'calendar_id' => $id]);
            $calendar->fill([
                'name' => mb_substr((string) ($item['summaryOverride'] ?? $item['summary'] ?? $id), 0, 255),
                'color' => isset($item['backgroundColor']) ? mb_substr((string) $item['backgroundColor'], 0, 16) : null,
                'is_primary' => (bool) ($item['primary'] ?? false),
            ])->save();
        }

        $known->each(function (GoogleCalendar $calendar) use ($items) {
            if (! $items->has($calendar->calendar_id)) {
                $calendar->delete();
            }
        });
    }

    /**
     * Which calendars are synced. A calendar no longer chosen loses its
     * copied events (tables made from them stay as they are).
     *
     * @param  list<int>  $ids  ids of GoogleCalendar rows of this connection
     */
    public function choose(GoogleConnection $connection, array $ids): void
    {
        foreach ($connection->calendars()->get() as $calendar) {
            $selected = in_array($calendar->id, $ids, true);

            if ($calendar->selected && ! $selected) {
                $calendar->events()->delete();
                $calendar->forceFill(['sync_token' => null, 'synced_at' => null]);
            }

            $calendar->forceFill(['selected' => $selected])->save();
        }
    }

    /** Forgets the account: tokens, calendars and copied events; tables made from them stay. */
    public function disconnect(GoogleConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;
        if (filled($token)) {
            $this->google->revoke($token);
        }

        $connection->delete();
    }
}
