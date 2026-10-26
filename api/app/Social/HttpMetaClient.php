<?php

namespace App\Social;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Login and the Graph API ("Instagram API with Facebook Login")
 * over HTTPS. Only reads. Every call carries appsecret_proof, so a leaked
 * token is useless without the app secret.
 *
 * Insights metrics change often (Meta renames and drops them between
 * versions): a metric Meta refuses is left out, the others still come.
 */
class HttpMetaClient implements MetaClient
{
    /**
     * What a business grants on Meta's login page. Each needs Advanced
     * Access through App Review before businesses outside the app's own
     * team can connect (see the check instructions).
     */
    public const SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'read_insights',
        'instagram_basic',
        'instagram_manage_insights',
        // Pages owned through a Business Portfolio are listed only with it.
        'business_management',
    ];

    /** Error codes of Meta's rate limits (app, user, Page, Instagram account). */
    private const RATE_LIMIT_CODES = [4, 17, 32, 613, 80001, 80002, 80005, 80006, 80008];

    private const INSTAGRAM_METRICS = ['reach', 'views', 'accounts_engaged', 'total_interactions', 'likes', 'comments', 'shares', 'saves', 'replies', 'profile_links_taps'];

    private const MEDIA_METRICS = ['reach', 'views', 'saved', 'shares', 'total_interactions'];

    // page_impressions* were dropped in November 2025; views replaced them.
    private const PAGE_METRICS = ['page_post_engagements', 'page_media_view', 'page_total_media_view_unique', 'page_daily_follows_unique', 'page_daily_unfollows_unique', 'page_views_total'];

    public function __construct(
        private readonly ?string $appId,
        private readonly ?string $appSecret,
        private readonly ?string $redirectUri,
        private readonly string $version = 'v26.0',
        // A «Facebook Login for Business» configuration: asks for what it lists instead of SCOPES.
        private readonly ?string $configId = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->appId) && filled($this->appSecret) && filled($this->redirectUri);
    }

    public function authUrl(string $state): string
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'state' => $state,
            // Asks again for permissions the person declined last time.
            'auth_type' => 'rerequest',
        ];
        $params += filled($this->configId)
            ? ['config_id' => $this->configId]
            : ['scope' => implode(',', self::SCOPES)];

        return "https://www.facebook.com/{$this->version}/dialog/oauth?".http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): MetaToken
    {
        // The code gives a token for an hour or two...
        $short = $this->token([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        // ...and that one a long-lived token (about 60 days). Page tokens
        // read with it don't expire while the person keeps the access.
        return $this->token([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $short->accessToken,
        ]);
    }

    public function me(string $userToken): array
    {
        $json = $this->get($userToken, '/me', ['fields' => 'id,name']);

        return ['id' => (string) ($json['id'] ?? ''), 'name' => $json['name'] ?? null];
    }

    public function grantedPermissions(string $userToken): array
    {
        try {
            $json = $this->get($userToken, '/me/permissions');
        } catch (MetaAuthException $e) {
            throw $e;
        } catch (MetaException) {
            return [];
        }

        return collect($json['data'] ?? [])
            ->filter(fn ($p) => ($p['status'] ?? null) === 'granted')
            ->pluck('permission')
            ->values()
            ->all();
    }

    public function pages(string $userToken): array
    {
        $fields = 'id,name,access_token,category,link,followers_count,picture{url},'
            .'instagram_business_account{id,username,name,profile_picture_url,followers_count,follows_count,media_count,biography,website}';

        return array_map(fn (array $page) => [
            'id' => (string) $page['id'],
            'name' => $page['name'] ?? null,
            'token' => $page['access_token'] ?? null,
            'category' => $page['category'] ?? null,
            'link' => $page['link'] ?? null,
            'picture' => $page['picture']['data']['url'] ?? null,
            'followers' => self::int($page['followers_count'] ?? null),
            'instagram' => isset($page['instagram_business_account']['id'])
                ? self::instagram($page['instagram_business_account'])
                : null,
        ], $this->paged($userToken, '/me/accounts', ['fields' => $fields, 'limit' => 100], 500));
    }

    public function instagramProfile(string $token, string $instagramId): array
    {
        return self::instagram($this->get($token, '/'.$instagramId, [
            'fields' => 'id,username,name,profile_picture_url,followers_count,follows_count,media_count,biography,website',
        ]));
    }

    public function instagramInsights(string $token, string $instagramId, CarbonImmutable $since, CarbonImmutable $until): array
    {
        return $this->insights($token, '/'.$instagramId.'/insights', self::INSTAGRAM_METRICS, [
            'period' => 'day',
            'metric_type' => 'total_value',
            'since' => $since->timestamp,
            'until' => $until->timestamp,
        ], fn (array $item) => self::int($item['total_value']['value'] ?? null));
    }

    public function instagramAudience(string $token, string $instagramId): array
    {
        $audience = [];

        foreach (['age', 'gender', 'city', 'country'] as $breakdown) {
            try {
                $json = $this->get($token, '/'.$instagramId.'/insights', [
                    'metric' => 'follower_demographics',
                    'period' => 'lifetime',
                    'metric_type' => 'total_value',
                    'timeframe' => 'this_month',
                    'breakdown' => $breakdown,
                ]);
            } catch (MetaAuthException|MetaRateLimited $e) {
                throw $e;
            } catch (MetaException) {
                // Fewer than 100 followers, or the breakdown isn't given: nothing for it.
                continue;
            }

            $results = $json['data'][0]['total_value']['breakdowns'][0]['results'] ?? [];
            $groups = [];
            foreach ($results as $result) {
                $key = (string) ($result['dimension_values'][0] ?? '');
                if ($key !== '') {
                    $groups[$key] = (int) ($result['value'] ?? 0);
                }
            }
            arsort($groups);
            if ($groups !== []) {
                $audience[$breakdown] = array_slice($groups, 0, 15, true);
            }
        }

        return $audience;
    }

    public function instagramMedia(string $token, string $instagramId, int $limit): array
    {
        $items = $this->paged($token, '/'.$instagramId.'/media', [
            'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count',
            'limit' => min(50, $limit),
        ], $limit);

        return array_map(function (array $item) use ($token) {
            $post = self::post($item);
            $kind = ($item['media_product_type'] ?? null) === 'REELS' ? 'REELS' : ($item['media_type'] ?? null);
            $post['kind'] = $kind;

            $insights = $this->insights($token, '/'.$item['id'].'/insights', self::MEDIA_METRICS, [], fn (array $i) => self::int($i['values'][0]['value'] ?? $i['total_value']['value'] ?? null), quiet: true);

            return [
                ...$post,
                'reach' => $insights['reach'] ?? null,
                'views' => $insights['views'] ?? null,
                'saves' => $insights['saved'] ?? null,
                'shares' => $insights['shares'] ?? null,
                'interactions' => $insights['total_interactions'] ?? null,
            ];
        }, $items);
    }

    public function pageProfile(string $token, string $pageId): array
    {
        $json = $this->get($token, '/'.$pageId, ['fields' => 'id,name,link,category,followers_count,picture{url}']);

        return [
            'id' => (string) $json['id'],
            'name' => $json['name'] ?? null,
            'link' => $json['link'] ?? null,
            'picture' => $json['picture']['data']['url'] ?? null,
            'followers' => self::int($json['followers_count'] ?? null),
            'category' => $json['category'] ?? null,
        ];
    }

    public function pageInsights(string $token, string $pageId, CarbonImmutable $since, CarbonImmutable $until): array
    {
        // Day by day: the period's total is their sum.
        return $this->insights($token, '/'.$pageId.'/insights', self::PAGE_METRICS, [
            'period' => 'day',
            'since' => $since->timestamp,
            'until' => $until->timestamp,
        ], fn (array $item) => collect($item['values'] ?? [])->sum(fn ($v) => is_numeric($v['value'] ?? null) ? (int) $v['value'] : 0));
    }

    public function pagePosts(string $token, string $pageId, int $limit): array
    {
        $items = $this->paged($token, '/'.$pageId.'/posts', [
            'fields' => 'id,message,created_time,permalink_url,shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)',
            'limit' => min(50, $limit),
        ], $limit);

        return array_map(fn (array $item) => [
            'id' => (string) $item['id'],
            'kind' => 'post',
            'caption' => isset($item['message']) ? mb_substr((string) $item['message'], 0, 2000) : null,
            'permalink' => $item['permalink_url'] ?? null,
            'postedAt' => $item['created_time'] ?? null,
            'likes' => self::int($item['reactions']['summary']['total_count'] ?? null),
            'comments' => self::int($item['comments']['summary']['total_count'] ?? null),
            'shares' => self::int($item['shares']['count'] ?? 0),
            'saves' => null,
            'reach' => null,
            'views' => null,
            'interactions' => null,
        ], $items);
    }

    public function businessDiscovery(string $token, string $instagramId, string $username, int $postLimit): array
    {
        // It goes into the fields expression: only what a username can be.
        if (! preg_match('/^[A-Za-z0-9._]{1,30}$/', $username)) {
            throw new MetaNotFound('Not an Instagram username.');
        }

        $fields = sprintf(
            'business_discovery.username(%s){id,username,name,biography,website,profile_picture_url,followers_count,follows_count,media_count,media.limit(%d){id,caption,media_type,media_product_type,like_count,comments_count,timestamp,permalink}}',
            $username,
            max(1, min(50, $postLimit)),
        );

        try {
            $json = $this->get($token, '/'.$instagramId, ['fields' => $fields]);
        } catch (MetaAuthException|MetaRateLimited $e) {
            throw $e;
        } catch (MetaException $e) {
            // 110: no such user; 100 / 2207013: not a business or creator account.
            if (in_array($e->metaCode, [100, 110], true)) {
                throw new MetaNotFound($e->getMessage(), $e->metaCode, $e->metaSubcode, $e);
            }
            throw $e;
        }

        $found = $json['business_discovery'] ?? null;
        if (! is_array($found)) {
            throw new MetaNotFound('No business discovery result.');
        }

        return [
            'profile' => self::instagram($found),
            'posts' => array_map(function (array $item) {
                $post = self::post($item);
                $post['kind'] = ($item['media_product_type'] ?? null) === 'REELS' ? 'REELS' : ($item['media_type'] ?? null);

                return $post;
            }, $found['media']['data'] ?? []),
        ];
    }

    public function revoke(string $userToken, string $metaUserId): void
    {
        try {
            Http::timeout(10)->delete($this->url('/'.$metaUserId.'/permissions'), $this->auth($userToken));
        } catch (ConnectionException) {
            // Deleted on our side anyway.
        }
    }

    /**
     * Totals of several metrics in one call; when Meta refuses the call
     * because of one metric, each is asked alone and the refused ones are
     * left out.
     *
     * @param  list<string>  $metrics
     * @return array<string, int>
     */
    private function insights(string $token, string $path, array $metrics, array $params, callable $value, bool $quiet = false): array
    {
        $read = function (array $json) use ($value) {
            $totals = [];
            foreach ($json['data'] ?? [] as $item) {
                $total = $value($item);
                if (isset($item['name']) && $total !== null) {
                    $totals[$item['name']] = $total;
                }
            }

            return $totals;
        };

        try {
            return $read($this->get($token, $path, ['metric' => implode(',', $metrics), ...$params]));
        } catch (MetaAuthException|MetaRateLimited $e) {
            throw $e;
        } catch (MetaException $e) {
            if ($e->metaCode !== 100 && $e->metaCode !== 10 && ! $quiet) {
                throw $e;
            }
        }

        $totals = [];
        foreach ($metrics as $metric) {
            try {
                $totals += $read($this->get($token, $path, ['metric' => $metric, ...$params]));
            } catch (MetaAuthException|MetaRateLimited $e) {
                throw $e;
            } catch (MetaException) {
                // Not given for this account or this version: left out.
            }
        }

        return $totals;
    }

    /**
     * Follows `paging.next` until $max items.
     *
     * @return list<array<string, mixed>>
     */
    private function paged(string $token, string $path, array $params, int $max): array
    {
        $items = [];
        $after = null;

        do {
            $json = $this->get($token, $path, array_filter([...$params, 'after' => $after]));
            array_push($items, ...($json['data'] ?? []));
            $after = isset($json['paging']['next']) ? ($json['paging']['cursors']['after'] ?? null) : null;
        } while ($after !== null && count($items) < $max);

        return array_slice($items, 0, $max);
    }

    private function token(array $query): MetaToken
    {
        try {
            $response = Http::acceptJson()->timeout(20)->get($this->url('/oauth/access_token'), $query);
        } catch (ConnectionException $e) {
            throw new MetaException('Meta is not reachable.', previous: $e);
        }

        $json = $this->json($response);

        return new MetaToken((string) ($json['access_token'] ?? ''), isset($json['expires_in']) ? (int) $json['expires_in'] : null);
    }

    private function get(string $token, string $path, array $params = []): array
    {
        try {
            $response = Http::acceptJson()->timeout(30)->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get($this->url($path), [...$params, ...$this->auth($token)]);
        } catch (ConnectionException $e) {
            throw new MetaException('Meta is not reachable.', previous: $e);
        }

        $this->watchUsage($response);

        return $this->json($response);
    }

    /** @return array{access_token: string, appsecret_proof: string} */
    private function auth(string $token): array
    {
        return ['access_token' => $token, 'appsecret_proof' => hash_hmac('sha256', $token, (string) $this->appSecret)];
    }

    private function url(string $path): string
    {
        return "https://graph.facebook.com/{$this->version}{$path}";
    }

    private function json(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
        $message = 'Meta error '.$response->status().': '.($error['message'] ?? 'unknown');

        throw match (true) {
            // 190: invalid or expired token; 102: session; 463/467: expired.
            $code === 190 || $code === 102 || in_array($subcode, [458, 460, 463, 467], true) => new MetaAuthException($message, $code, $subcode),
            in_array($code, self::RATE_LIMIT_CODES, true) => new MetaRateLimited($message, $code, $subcode),
            default => new MetaException($message, $code, $subcode),
        };
    }

    /** Meta says how much of its limits the app used; logged when it gets close. */
    private function watchUsage(Response $response): void
    {
        foreach (['X-App-Usage', 'X-Business-Use-Case-Usage'] as $header) {
            $value = $response->header($header);
            if ($value !== '' && preg_match_all('/"(call_count|total_time|total_cputime)":\s*(\d+)/', $value, $m) && max(array_map('intval', $m[2])) >= 80) {
                Log::warning('Meta API usage is close to the limit', ['header' => $header, 'usage' => $value]);
            }
        }
    }

    /** @return array{id: string, username: ?string, name: ?string, picture: ?string, followers: ?int, follows: ?int, mediaCount: ?int, biography: ?string, website: ?string} */
    private static function instagram(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'username' => $item['username'] ?? null,
            'name' => $item['name'] ?? null,
            'picture' => $item['profile_picture_url'] ?? null,
            'followers' => self::int($item['followers_count'] ?? null),
            'follows' => self::int($item['follows_count'] ?? null),
            'mediaCount' => self::int($item['media_count'] ?? null),
            'biography' => $item['biography'] ?? null,
            'website' => $item['website'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private static function post(array $item): array
    {
        return [
            'id' => (string) $item['id'],
            'kind' => $item['media_type'] ?? null,
            'caption' => isset($item['caption']) ? mb_substr((string) $item['caption'], 0, 2000) : null,
            'permalink' => $item['permalink'] ?? null,
            'postedAt' => $item['timestamp'] ?? null,
            // Hidden by the account's owner: Meta leaves like_count out.
            'likes' => self::int($item['like_count'] ?? null),
            'comments' => self::int($item['comments_count'] ?? null),
        ];
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
