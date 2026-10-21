<?php

namespace App\Google;

/**
 * Google's OAuth and Calendar API, read only: what the calendar sync needs.
 * Tests swap in a fake (tests/Fakes/FakeGoogleCalendarClient), so they never
 * reach the network.
 */
interface GoogleCalendarClient
{
    /** The platform's OAuth keys are set (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI). */
    public function isConfigured(): bool;

    /** Google's consent page; it comes back to the redirect URI with a code and this state. */
    public function authUrl(string $state): string;

    /** @throws GoogleException */
    public function exchangeCode(string $code): GoogleToken;

    /** @throws GoogleAuthException when Google refuses the refresh token (access revoked) */
    public function refresh(string $refreshToken): GoogleToken;

    /** Tells Google to forget the access (on disconnect); best effort. */
    public function revoke(string $token): void;

    /**
     * The account's calendars.
     *
     * @return list<array{id: string, summary?: string, summaryOverride?: string, backgroundColor?: string, primary?: bool, accessRole?: string}>
     *
     * @throws GoogleException
     */
    public function calendars(string $accessToken): array;

    /**
     * One page of a calendar's events, recurring ones as single instances.
     * With a sync token: only what changed since (deleted events come with
     * status "cancelled"); without: the events between $timeMin and $timeMax.
     *
     * @param  array{syncToken?: ?string, pageToken?: ?string, timeMin?: ?string, timeMax?: ?string}  $query
     *
     * @throws GoogleSyncTokenExpired when Google no longer knows the sync token (a full sync is due)
     * @throws GoogleException
     */
    public function events(string $accessToken, string $calendarId, array $query): GoogleEventsPage;
}
