<?php

namespace Tests\Fakes;

use App\Google\GoogleAuthException;
use App\Google\GoogleCalendarClient;
use App\Google\GoogleEventsPage;
use App\Google\GoogleSyncTokenExpired;
use App\Google\GoogleToken;

/**
 * Google in tests: calendars and events set by the test, sync tokens that
 * hand back the changes set in `changes`, and every call written down.
 */
class FakeGoogleCalendarClient implements GoogleCalendarClient
{
    public bool $configured = true;

    public string $email = 'owner@gmail.com';

    /** @var list<array> */
    public array $calendars = [];

    /** @var array<string, list<array>> events of a full read, by calendar id */
    public array $events = [];

    /** @var array<string, list<array>> what a read with a sync token returns, by calendar id */
    public array $changes = [];

    /** @var array<string, bool> calendars whose sync token Google forgot */
    public array $expiredTokens = [];

    public int $pageSize = 250;

    public bool $refreshRevoked = false;

    /** @var list<array> */
    public array $calls = [];

    private int $tokens = 0;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?state='.rawurlencode($state);
    }

    public function exchangeCode(string $code): GoogleToken
    {
        $this->calls[] = ['exchange', $code];

        return new GoogleToken('access-'.(++$this->tokens), 'refresh-'.$this->tokens, 3600, 'calendar.readonly', $this->email);
    }

    public function refresh(string $refreshToken): GoogleToken
    {
        $this->calls[] = ['refresh', $refreshToken];

        if ($this->refreshRevoked) {
            throw new GoogleAuthException('revoked');
        }

        return new GoogleToken('access-'.(++$this->tokens), null, 3600);
    }

    public function revoke(string $token): void
    {
        $this->calls[] = ['revoke', $token];
    }

    public function calendars(string $accessToken): array
    {
        $this->calls[] = ['calendars', $accessToken];

        return $this->calendars;
    }

    public function events(string $accessToken, string $calendarId, array $query): GoogleEventsPage
    {
        $syncToken = $query['syncToken'] ?? null;
        $this->calls[] = ['events', $calendarId, $syncToken === null ? 'full' : 'changes', $query['pageToken'] ?? null];

        if ($syncToken !== null && ($this->expiredTokens[$calendarId] ?? false)) {
            unset($this->expiredTokens[$calendarId]);
            throw new GoogleSyncTokenExpired('gone');
        }

        $items = $syncToken === null ? ($this->events[$calendarId] ?? []) : ($this->changes[$calendarId] ?? []);
        $offset = (int) ($query['pageToken'] ?? 0);
        $page = array_slice($items, $offset, $this->pageSize);
        $more = $offset + $this->pageSize < count($items);

        return new GoogleEventsPage(
            $page,
            $more ? (string) ($offset + $this->pageSize) : null,
            $more ? null : "sync-{$calendarId}-".count($this->calls),
        );
    }
}
