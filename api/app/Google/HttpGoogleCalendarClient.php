<?php

namespace App\Google;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google's OAuth 2.0 web flow and Calendar API v3 over HTTPS. Only reads:
 * the scope asked for is calendar.readonly (plus openid email, to show
 * which account is connected).
 */
class HttpGoogleCalendarClient implements GoogleCalendarClient
{
    public const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/calendar.readonly'];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $redirectUri,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret) && filled($this->redirectUri);
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            // A refresh token, so syncing works after the hour an access token lasts.
            'access_type' => 'offline',
            // Asks every time: the person picks the account, and Google sends a refresh token again.
            'prompt' => 'consent select_account',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): GoogleToken
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);
    }

    public function refresh(string $refreshToken): GoogleToken
    {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    public function revoke(string $token): void
    {
        try {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $token]);
        } catch (ConnectionException) {
            // The tokens are deleted on our side anyway.
        }
    }

    public function calendars(string $accessToken): array
    {
        $items = [];
        $pageToken = null;

        do {
            $json = $this->get($accessToken, '/users/me/calendarList', array_filter([
                'maxResults' => 250,
                'pageToken' => $pageToken,
            ]));
            array_push($items, ...($json['items'] ?? []));
            $pageToken = $json['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $items;
    }

    public function events(string $accessToken, string $calendarId, array $query): GoogleEventsPage
    {
        $params = ['singleEvents' => 'true', 'maxResults' => 250, 'showDeleted' => 'true'];

        if (filled($query['syncToken'] ?? null)) {
            $params['syncToken'] = $query['syncToken'];
        } else {
            $params += array_filter(['timeMin' => $query['timeMin'] ?? null, 'timeMax' => $query['timeMax'] ?? null]);
        }
        if (filled($query['pageToken'] ?? null)) {
            $params['pageToken'] = $query['pageToken'];
        }

        $json = $this->get($accessToken, '/calendars/'.rawurlencode($calendarId).'/events', $params);

        return new GoogleEventsPage($json['items'] ?? [], $json['nextPageToken'] ?? null, $json['nextSyncToken'] ?? null);
    }

    private function token(array $fields): GoogleToken
    {
        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                ...$fields,
            ]);
        } catch (ConnectionException $e) {
            throw new GoogleException('Google is not reachable.', previous: $e);
        }

        if ($response->failed()) {
            $error = (string) $response->json('error');
            // invalid_grant: the code was used, or the refresh token was revoked.
            throw $error === 'invalid_grant'
                ? new GoogleAuthException('Google refused the grant.')
                : new GoogleException("Google token error: {$error}");
        }

        return new GoogleToken(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresIn: (int) ($response->json('expires_in') ?? 3600),
            scope: $response->json('scope'),
            email: self::emailFromIdToken($response->json('id_token')),
        );
    }

    private function get(string $accessToken, string $path, array $params): array
    {
        try {
            $response = $this->api($accessToken)->get(self::API.$path, $params);
        } catch (ConnectionException $e) {
            throw new GoogleException('Google is not reachable.', previous: $e);
        }

        return $this->json($response);
    }

    private function api(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson()->timeout(30)->retry(2, 500, throw: false);
    }

    private function json(Response $response): array
    {
        return match (true) {
            $response->successful() => $response->json() ?? [],
            $response->status() === 410 => throw new GoogleSyncTokenExpired('The sync token expired.'),
            $response->status() === 401 => throw new GoogleAuthException('Google refused the access token.'),
            default => throw new GoogleException('Google Calendar error '.$response->status().': '.$response->json('error.message')),
        };
    }

    /**
     * The address in an id token. It came straight from Google's token
     * endpoint over TLS, so its payload is read without checking the signature.
     */
    private static function emailFromIdToken(mixed $idToken): ?string
    {
        if (! is_string($idToken) || substr_count($idToken, '.') !== 2) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr(explode('.', $idToken)[1], '-_', '+/')), true);

        return is_array($payload) && is_string($payload['email'] ?? null) ? $payload['email'] : null;
    }
}
