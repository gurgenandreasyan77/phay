<?php

namespace App\Social;

use App\Models\SocialAccount;
use App\Models\SocialAccountDay;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Reads the business's selected accounts from Meta into our tables:
 * profile and followers (one row a day, for the growth), totals of the last
 * 28 days and of the 28 before, who the followers are, the latest posts with
 * their figures; then the watched accounts that are due. Meta is read on a
 * schedule (platform:social:sync) and on «Refresh», never while the
 * assistant answers.
 */
class SocialSync
{
    /** Posts read per account each time (older ones keep their last figures). */
    public const POSTS = 50;

    /** Insights over this many days (Meta allows at most 30 between since and until). */
    public const WINDOW_DAYS = 28;

    /** «Refresh» at most this often: Meta limits calls per account. */
    public const COOLDOWN_MINUTES = 10;

    public function __construct(
        private readonly MetaClient $meta,
        private readonly MetaAccounts $accounts,
        private readonly Competitors $competitors,
        private readonly TenantContext $tenants,
    ) {}

    public function coolingDown(SocialConnection $connection): bool
    {
        return $connection->synced_at !== null && $connection->synced_at->isAfter(now()->subMinutes(self::COOLDOWN_MINUTES));
    }

    /**
     * @return array{accounts: int, posts: int, competitors: array{read: int, notFound: int, failed: int}}
     *
     * @throws MetaAuthException when Meta no longer accepts the access (marked on the connection)
     * @throws MetaRateLimited when Meta's limit is reached (the figures read so far are kept)
     * @throws MetaException
     */
    public function sync(SocialConnection $connection, bool $allCompetitors = false): array
    {
        if ($connection->revoked_at !== null) {
            throw new MetaAuthException('The Meta access was revoked.');
        }

        $result = ['accounts' => 0, 'posts' => 0, 'competitors' => ['read' => 0, 'notFound' => 0, 'failed' => 0]];

        try {
            // Fresh Page tokens and the list of Pages, then each chosen account.
            $this->accounts->refreshAccounts($connection);

            foreach ($connection->accounts()->selected()->get() as $account) {
                $account->setRelation('connection', $connection);
                $result['posts'] += $account->isInstagram() ? $this->instagram($account) : $this->facebook($account);
                $result['accounts']++;
            }

            $result['competitors'] = $this->competitors->syncDue($allCompetitors);
            $connection->forceFill(['synced_at' => now(), 'sync_error' => null])->save();
        } catch (MetaRateLimited $e) {
            $connection->forceFill(['synced_at' => now(), 'sync_error' => 'rate_limited'])->save();
            throw $e;
        } catch (MetaAuthException $e) {
            throw $e;
        } catch (MetaException $e) {
            $connection->forceFill(['sync_error' => Str::limit($e->getMessage(), 490)])->save();
            throw $e;
        } finally {
            $this->accounts->warnIfExpiring($connection);
        }

        return $result;
    }

    private function instagram(SocialAccount $account): int
    {
        $token = $this->accounts->tokenFor($account);
        $id = $account->external_id;
        $call = fn (callable $c) => $this->accounts->call($account->connection, $c);

        $profile = $call(fn () => $this->meta->instagramProfile($token, $id));
        [$current, $previous, $window] = $this->windows();
        $insights = [
            ...$window,
            'current' => $call(fn () => $this->meta->instagramInsights($token, $id, $current[0], $current[1])),
            'previous' => $call(fn () => $this->meta->instagramInsights($token, $id, $previous[0], $previous[1])),
        ];

        $account->fill([
            'username' => $profile['username'] ?? $account->username,
            'name' => $profile['name'] ?? $account->name,
            'picture_url' => $profile['picture'] ?? $account->picture_url,
            'followers' => $profile['followers'],
            'follows' => $profile['follows'],
            'media_count' => $profile['mediaCount'],
            'profile' => array_filter(['biography' => $profile['biography'], 'website' => $profile['website']]),
            'insights' => $insights,
        ]);

        // Who the followers are changes slowly: once a day.
        if ($account->audience_synced_at === null || $account->audience_synced_at->isBefore(now()->subDay())) {
            $account->audience = $call(fn () => $this->meta->instagramAudience($token, $id)) ?: null;
            $account->audience_synced_at = now();
        }

        $posts = $call(fn () => $this->meta->instagramMedia($token, $id, self::POSTS));

        return $this->finish($account, $posts);
    }

    private function facebook(SocialAccount $account): int
    {
        $token = $this->accounts->tokenFor($account);
        $id = $account->external_id;
        $call = fn (callable $c) => $this->accounts->call($account->connection, $c);

        $profile = $call(fn () => $this->meta->pageProfile($token, $id));
        [$current, $previous, $window] = $this->windows();

        $account->fill([
            'name' => $profile['name'] ?? $account->name,
            'picture_url' => $profile['picture'] ?? $account->picture_url,
            'followers' => $profile['followers'],
            'profile' => array_filter(['category' => $profile['category'], 'link' => $profile['link']]),
            'insights' => [
                ...$window,
                'current' => $call(fn () => $this->meta->pageInsights($token, $id, $current[0], $current[1])),
                'previous' => $call(fn () => $this->meta->pageInsights($token, $id, $previous[0], $previous[1])),
            ],
        ]);

        $posts = $call(fn () => $this->meta->pagePosts($token, $id, self::POSTS));

        return $this->finish($account, $posts);
    }

    /** Keeps the posts, the day's followers and the time. */
    private function finish(SocialAccount $account, array $posts): int
    {
        foreach ($posts as $post) {
            if (empty($post['id']) || empty($post['postedAt'])) {
                continue;
            }

            SocialPost::query()->updateOrCreate(
                ['social_account_id' => $account->id, 'external_id' => $post['id']],
                [
                    'kind' => $post['kind'] ?? null,
                    'caption' => $post['caption'] ?? null,
                    'permalink' => isset($post['permalink']) ? Str::limit((string) $post['permalink'], 990, '') : null,
                    'posted_at' => CarbonImmutable::parse($post['postedAt'])->utc(),
                    ...collect(['likes', 'comments', 'shares', 'saves', 'reach', 'views', 'interactions'])
                        ->mapWithKeys(fn ($k) => [$k => $post[$k] ?? null])->all(),
                ],
            );
        }

        SocialAccountDay::query()->updateOrCreate(
            ['social_account_id' => $account->id, 'date' => CarbonImmutable::now($this->timezone())->toDateString()],
            ['followers' => $account->followers, 'media_count' => $account->media_count],
        );

        $account->synced_at = now();
        $account->save();

        return count($posts);
    }

    /**
     * The last WINDOW_DAYS days up to now and the same length before them.
     *
     * @return array{0: array{0: CarbonImmutable, 1: CarbonImmutable}, 1: array{0: CarbonImmutable, 1: CarbonImmutable}, 2: array{from: string, to: string, previousFrom: string, previousTo: string}}
     */
    private function windows(): array
    {
        $tz = $this->timezone();
        $until = CarbonImmutable::now($tz);
        $since = $until->startOfDay()->subDays(self::WINDOW_DAYS - 1);
        $previousUntil = $since;
        $previousSince = $since->subDays(self::WINDOW_DAYS);

        return [
            [$since, $until],
            [$previousSince, $previousUntil],
            [
                'from' => $since->toDateString(),
                'to' => $until->toDateString(),
                'previousFrom' => $previousSince->toDateString(),
                'previousTo' => $previousUntil->subDay()->toDateString(),
            ],
        ];
    }

    private function timezone(): string
    {
        return $this->tenants->current()->timezone ?: config('app.timezone');
    }
}
