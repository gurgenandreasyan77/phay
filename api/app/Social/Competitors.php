<?php

namespace App\Social;

use App\Models\SocialAccount;
use App\Models\SocialCompetitor;
use App\Models\SocialCompetitorDay;
use App\Models\SocialConnection;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The Instagram accounts a business watches. Business Discovery reads only
 * what Instagram shows everyone (followers, posts, their likes and
 * comments) of another business or creator account, through the business's
 * own Instagram account; personal accounts can't be read at all.
 */
class Competitors
{
    public const MAX = 10;

    /** Posts read per account: enough for averages and the best times. */
    public const POSTS = 24;

    /** A watched account is read again after this many hours. */
    public const STALE_HOURS = 12;

    public function __construct(
        private readonly MetaClient $meta,
        private readonly MetaAccounts $accounts,
        private readonly TenantContext $tenants,
    ) {}

    /** "@Name.Shop", "instagram.com/name.shop/" → "name.shop"; null when it can't be a username. */
    public static function username(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('~instagram\.com/([^/?#\s]+)~i', $value, $m)) {
            $value = $m[1];
        }
        $value = Str::lower(ltrim($value, '@'));

        return preg_match('/^[a-z0-9._]{1,30}$/', $value) ? $value : null;
    }

    /**
     * Adds an account to watch and reads it right away when the business's
     * Instagram is connected.
     *
     * @throws InvalidArgumentException with a code: invalid, exists, limit
     */
    public function add(User $user, string $value): SocialCompetitor
    {
        $username = self::username($value) ?? throw new InvalidArgumentException('invalid');

        if (SocialCompetitor::query()->where('username', $username)->exists()) {
            throw new InvalidArgumentException('exists');
        }
        if (SocialCompetitor::query()->count() >= self::MAX) {
            throw new InvalidArgumentException('limit');
        }

        $own = $this->ownInstagram();
        if ($own && Str::lower((string) $own->username) === $username) {
            throw new InvalidArgumentException('own');
        }

        $competitor = SocialCompetitor::query()->create(['username' => $username, 'added_by' => $user->id]);

        if ($own) {
            try {
                $this->sync($competitor, $own);
            } catch (MetaException) {
                // Rate limit or a refused token: the next sync reads it.
            }
        }

        return $competitor->refresh();
    }

    /**
     * Reads the watched accounts not read for STALE_HOURS (all with $force).
     *
     * @return array{read: int, notFound: int, failed: int}
     *
     * @throws MetaRateLimited|MetaAuthException
     */
    public function syncDue(bool $force = false): array
    {
        $result = ['read' => 0, 'notFound' => 0, 'failed' => 0];
        $own = $this->ownInstagram();

        $due = SocialCompetitor::query()
            ->when(! $force, fn ($q) => $q->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', now()->subHours(self::STALE_HOURS))))
            ->orderBy('synced_at')
            ->get();

        foreach ($due as $competitor) {
            if ($own === null) {
                $competitor->forceFill(['status' => SocialCompetitor::ERROR, 'error' => 'needs_instagram'])->save();
                $result['failed']++;

                continue;
            }

            $status = $this->sync($competitor, $own);
            $result[match ($status) {
                SocialCompetitor::OK => 'read',
                SocialCompetitor::NOT_FOUND => 'notFound',
                default => 'failed',
            }]++;
        }

        return $result;
    }

    /**
     * Reads one account through the business's Instagram.
     *
     * @throws MetaRateLimited|MetaAuthException
     */
    public function sync(SocialCompetitor $competitor, SocialAccount $own): string
    {
        $connection = $own->connection;

        try {
            $found = $this->accounts->call($connection, fn () => $this->meta->businessDiscovery($this->accounts->tokenFor($own), $own->external_id, $competitor->username, self::POSTS));
        } catch (MetaNotFound) {
            $competitor->forceFill(['status' => SocialCompetitor::NOT_FOUND, 'error' => null, 'synced_at' => now()])->save();

            return SocialCompetitor::NOT_FOUND;
        } catch (MetaRateLimited|MetaAuthException $e) {
            throw $e;
        } catch (MetaException $e) {
            $competitor->forceFill(['status' => SocialCompetitor::ERROR, 'error' => Str::limit($e->getMessage(), 490), 'synced_at' => now()])->save();

            return SocialCompetitor::ERROR;
        }

        $this->fill($competitor, $found);

        return SocialCompetitor::OK;
    }

    /**
     * An account the business doesn't watch, read once for the assistant
     * and kept for STALE_HOURS (not added to the list).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the username is not one, or Instagram isn't connected
     */
    public function lookup(string $value): array
    {
        $username = self::username($value) ?? throw new InvalidArgumentException('Not an Instagram username.');
        $own = $this->ownInstagram() ?? throw new InvalidArgumentException('The business has not connected its Instagram professional account, so other accounts cannot be read.');

        return Cache::remember('social:lookup:'.$this->tenants->id().':'.$username, now()->addHours(self::STALE_HOURS), function () use ($own, $username) {
            try {
                $found = $this->accounts->call($own->connection, fn () => $this->meta->businessDiscovery($this->accounts->tokenFor($own), $own->external_id, $username, self::POSTS));
            } catch (MetaNotFound) {
                return ['username' => $username, 'status' => SocialCompetitor::NOT_FOUND];
            }

            $profile = $found['profile'];

            return [
                'username' => $username,
                'status' => SocialCompetitor::OK,
                'name' => $profile['name'],
                'biography' => $profile['biography'],
                'website' => $profile['website'],
                'followers' => $profile['followers'],
                'follows' => $profile['follows'],
                'mediaCount' => $profile['mediaCount'],
                'stats' => PostStats::of($found['posts'], $profile['followers'], $this->timezone()),
                'recentPosts' => self::recent($found['posts']),
            ];
        });
    }

    /** The business's Instagram professional account that reads the others: the first selected one. */
    public function ownInstagram(): ?SocialAccount
    {
        $connection = SocialConnection::query()->whereNull('revoked_at')->first();

        return $connection?->accounts()->selected()->where('network', SocialAccount::INSTAGRAM)->orderBy('id')->first()?->setRelation('connection', $connection);
    }

    /** @param array{profile: array<string, mixed>, posts: list<array<string, mixed>>} $found */
    private function fill(SocialCompetitor $competitor, array $found): void
    {
        $profile = $found['profile'];

        $competitor->forceFill([
            'username' => $profile['username'] ? Str::lower($profile['username']) : $competitor->username,
            'name' => $profile['name'],
            'biography' => $profile['biography'],
            'website' => $profile['website'] ? Str::limit($profile['website'], 490, '') : null,
            'picture_url' => $profile['picture'],
            'followers' => $profile['followers'],
            'follows' => $profile['follows'],
            'media_count' => $profile['mediaCount'],
            'stats' => PostStats::of($found['posts'], $profile['followers'], $this->timezone()),
            'recent_posts' => self::recent($found['posts']),
            'status' => SocialCompetitor::OK,
            'error' => null,
            'synced_at' => now(),
        ])->save();

        SocialCompetitorDay::query()->updateOrCreate(
            ['social_competitor_id' => $competitor->id, 'date' => CarbonImmutable::now($this->timezone())->toDateString()],
            ['followers' => $profile['followers'], 'media_count' => $profile['mediaCount']],
        );
    }

    /**
     * The latest posts, short: what the assistant compares.
     *
     * @return list<array<string, mixed>>
     */
    private static function recent(array $posts): array
    {
        return collect($posts)->take(12)->map(fn (array $p) => array_filter([
            'kind' => $p['kind'] ?? null,
            'postedAt' => $p['postedAt'] ?? null,
            'likes' => $p['likes'] ?? null,
            'comments' => $p['comments'] ?? null,
            'caption' => isset($p['caption']) ? Str::limit((string) $p['caption'], 300) : null,
            'permalink' => $p['permalink'] ?? null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    private function timezone(): string
    {
        return $this->tenants->current()->timezone ?: config('app.timezone');
    }
}
