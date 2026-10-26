<?php

namespace Tests\Fakes;

use App\Social\MetaClient;
use App\Social\MetaNotFound;
use App\Social\MetaToken;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Meta in tests: the Pages, Instagram accounts, insights, posts and other
 * accounts set by the test, an error to throw on a given call, and every
 * call written down.
 */
class FakeMetaClient implements MetaClient
{
    public bool $configured = true;

    public array $me = ['id' => 'fb-user-1', 'name' => 'Anna Owner'];

    public array $permissions = ['pages_show_list', 'pages_read_engagement', 'read_insights', 'instagram_basic', 'instagram_manage_insights', 'business_management'];

    public ?int $expiresIn = 5184000;

    /** @var list<array<string, mixed>> */
    public array $pages = [];

    /** @var array<string, array<string, mixed>> Instagram profiles by id */
    public array $profiles = [];

    /** @var array<string, int> */
    public array $insights = ['reach' => 1200, 'views' => 5400, 'accounts_engaged' => 140, 'total_interactions' => 380, 'likes' => 300, 'comments' => 45];

    /** @var array<string, int> */
    public array $previousInsights = ['reach' => 1000, 'views' => 4500];

    public array $audience = ['age' => ['25-34' => 120, '18-24' => 60], 'gender' => ['F' => 150, 'M' => 40], 'city' => ['Yerevan, Armenia' => 170]];

    /** @var array<string, list<array<string, mixed>>> posts by account id */
    public array $media = [];

    /** @var array<string, array{profile: array, posts: list<array>}> other accounts by username */
    public array $discovery = [];

    /** @var array<string, Throwable> thrown when that method is called */
    public array $throw = [];

    /** @var list<array> */
    public array $calls = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authUrl(string $state): string
    {
        return 'https://www.facebook.com/v26.0/dialog/oauth?state='.rawurlencode($state);
    }

    public function exchangeCode(string $code): MetaToken
    {
        $this->call(__FUNCTION__, $code);

        return new MetaToken('user-token-'.$code, $this->expiresIn);
    }

    public function me(string $userToken): array
    {
        $this->call(__FUNCTION__, $userToken);

        return $this->me;
    }

    public function grantedPermissions(string $userToken): array
    {
        return $this->permissions;
    }

    public function pages(string $userToken): array
    {
        $this->call(__FUNCTION__, $userToken);

        return $this->pages;
    }

    public function instagramProfile(string $token, string $instagramId): array
    {
        $this->call(__FUNCTION__, $token, $instagramId);

        return $this->profiles[$instagramId];
    }

    public function instagramInsights(string $token, string $instagramId, CarbonImmutable $since, CarbonImmutable $until): array
    {
        $this->call(__FUNCTION__, $token, $instagramId);

        // The older window is the one that ends before today.
        return $until->isBefore(now()->subDays(2)) ? $this->previousInsights : $this->insights;
    }

    public function instagramAudience(string $token, string $instagramId): array
    {
        $this->call(__FUNCTION__, $token, $instagramId);

        return $this->audience;
    }

    public function instagramMedia(string $token, string $instagramId, int $limit): array
    {
        $this->call(__FUNCTION__, $token, $instagramId);

        return $this->media[$instagramId] ?? [];
    }

    public function pageProfile(string $token, string $pageId): array
    {
        $this->call(__FUNCTION__, $token, $pageId);
        $page = collect($this->pages)->firstWhere('id', $pageId);

        return ['id' => $pageId, 'name' => $page['name'] ?? null, 'link' => $page['link'] ?? null, 'picture' => null, 'followers' => $page['followers'] ?? null, 'category' => $page['category'] ?? null];
    }

    public function pageInsights(string $token, string $pageId, CarbonImmutable $since, CarbonImmutable $until): array
    {
        $this->call(__FUNCTION__, $token, $pageId);

        return ['page_post_engagements' => 90, 'page_media_view' => 2100];
    }

    public function pagePosts(string $token, string $pageId, int $limit): array
    {
        $this->call(__FUNCTION__, $token, $pageId);

        return $this->media[$pageId] ?? [];
    }

    public function businessDiscovery(string $token, string $instagramId, string $username, int $postLimit): array
    {
        $this->call(__FUNCTION__, $token, $instagramId, $username);

        return $this->discovery[$username] ?? throw new MetaNotFound('Invalid user id', 110);
    }

    public function revoke(string $userToken, string $metaUserId): void
    {
        $this->calls[] = ['revoke', $userToken, $metaUserId];
    }

    /** @return list<array> calls of one method */
    public function callsOf(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }

    private function call(string $method, ...$args): void
    {
        $this->calls[] = [$method, ...$args];

        if (isset($this->throw[$method])) {
            throw $this->throw[$method];
        }
    }
}
