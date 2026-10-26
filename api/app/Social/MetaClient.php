<?php

namespace App\Social;

use Carbon\CarbonImmutable;

/**
 * Meta's login and Graph API, read only: what the social module needs from
 * Facebook Pages and Instagram professional accounts. Every answer is
 * already in our own shape (see HttpMetaClient). Tests swap in a fake
 * (tests/Fakes/FakeMetaClient), so they never reach the network.
 */
interface MetaClient
{
    /** The platform's Meta app keys are set (META_APP_ID, META_APP_SECRET, META_REDIRECT_URI). */
    public function isConfigured(): bool;

    /** Meta's login page; it comes back to the redirect URI with a code and this state. */
    public function authUrl(string $state): string;

    /**
     * The code from the login page as a long-lived user token (about 60 days).
     *
     * @throws MetaException
     */
    public function exchangeCode(string $code): MetaToken;

    /**
     * @return array{id: string, name: ?string}
     *
     * @throws MetaException
     */
    public function me(string $userToken): array;

    /**
     * The permissions the person granted (they can untick some on the login page).
     *
     * @return list<string>
     */
    public function grantedPermissions(string $userToken): array;

    /**
     * The Facebook Pages the person manages, each with its token and the
     * Instagram professional account linked to it, if any.
     *
     * @return list<array{id: string, name: ?string, token: ?string, category: ?string, link: ?string, picture: ?string, followers: ?int, instagram: ?array<string, mixed>}>
     *
     * @throws MetaException
     */
    public function pages(string $userToken): array;

    /**
     * @return array{id: string, username: ?string, name: ?string, picture: ?string, followers: ?int, follows: ?int, mediaCount: ?int, biography: ?string, website: ?string}
     *
     * @throws MetaException
     */
    public function instagramProfile(string $token, string $instagramId): array;

    /**
     * The account's totals between two days (at most 30 days): reach, views,
     * accounts_engaged, total_interactions, likes, comments, shares, saves,
     * replies, profile_links_taps. Metrics Meta doesn't give are left out.
     *
     * @return array<string, int>
     *
     * @throws MetaException
     */
    public function instagramInsights(string $token, string $instagramId, CarbonImmutable $since, CarbonImmutable $until): array;

    /**
     * Followers by age, gender, city and country (Meta gives them from 100 followers).
     *
     * @return array<string, array<string, int>>
     *
     * @throws MetaException
     */
    public function instagramAudience(string $token, string $instagramId): array;

    /**
     * The latest posts (no stories) with likes, comments and, when Meta gives
     * them, reach, views, saves, shares and interactions.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MetaException
     */
    public function instagramMedia(string $token, string $instagramId, int $limit): array;

    /**
     * @return array{id: string, name: ?string, link: ?string, picture: ?string, followers: ?int, category: ?string}
     *
     * @throws MetaException
     */
    public function pageProfile(string $token, string $pageId): array;

    /**
     * The Page's totals between two days; metrics Meta doesn't give are left out.
     *
     * @return array<string, int>
     *
     * @throws MetaException
     */
    public function pageInsights(string $token, string $pageId, CarbonImmutable $since, CarbonImmutable $until): array;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws MetaException
     */
    public function pagePosts(string $token, string $pageId, int $limit): array;

    /**
     * Another professional Instagram account's public profile and latest
     * posts, read through the business's own Instagram account.
     *
     * @return array{profile: array<string, mixed>, posts: list<array<string, mixed>>}
     *
     * @throws MetaNotFound when there is no business or creator account with that username
     * @throws MetaException
     */
    public function businessDiscovery(string $token, string $instagramId, string $username, int $postLimit): array;

    /** Tells Meta to forget the app's access (on disconnect); best effort. */
    public function revoke(string $userToken, string $metaUserId): void;
}
