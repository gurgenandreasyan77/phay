<?php

namespace App\Social;

use App\Features\FeatureFlag;
use App\Models\SocialAccount;
use App\Models\SocialAccountDay;
use App\Models\SocialCompetitor;
use App\Models\SocialCompetitorDay;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * The business's social figures as the screens and the assistant read
 * them, from what the last sync kept (no call to Meta here): each selected
 * account with its growth, the last 28 days against the 28 before, who
 * follows it and how its posts do; the watched accounts beside it.
 */
class SocialReport
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** The module is on for the business (the super admin turns it on). */
    public function enabled(): bool
    {
        return Feature::active(FeatureFlag::Social->value);
    }

    public function connection(): ?SocialConnection
    {
        return SocialConnection::query()->first();
    }

    /** @return EloquentCollection<int, SocialAccount> */
    public function selectedAccounts(): EloquentCollection
    {
        $connection = $this->connection();

        return $connection
            ? $connection->accounts()->selected()->orderByRaw("CASE WHEN network = 'instagram' THEN 0 ELSE 1 END")->orderBy('id')->get()
            : new EloquentCollection;
    }

    /** @return array<string, mixed> */
    public function account(SocialAccount $account, bool $withPosts = false): array
    {
        $tz = $this->timezone();
        $posts = $account->posts()->where('posted_at', '>=', now()->subDays(90))->orderByDesc('posted_at')->get();
        $insights = $account->insights ?? [];

        return array_filter([
            'id' => $account->id,
            'network' => $account->network,
            'handle' => $account->handle(),
            'username' => $account->username,
            'name' => $account->name,
            'picture' => $account->picture_url,
            'profile' => $account->profile ?: null,
            'url' => $account->isInstagram() && $account->username
                ? 'https://www.instagram.com/'.$account->username.'/'
                : ($account->profile['link'] ?? null),
            'followers' => $account->followers,
            'followersChange7Days' => $this->change(SocialAccountDay::class, 'social_account_id', $account->id, $account->followers, 7),
            'followersChange30Days' => $this->change(SocialAccountDay::class, 'social_account_id', $account->id, $account->followers, 30),
            'follows' => $account->follows,
            'mediaCount' => $account->media_count,
            'last28Days' => $insights === [] ? null : [
                'from' => $insights['from'] ?? null,
                'to' => $insights['to'] ?? null,
                'totals' => $insights['current'] ?? [],
                'previous28Days' => $insights['previous'] ?? [],
                'changePercent' => self::changes($insights['current'] ?? [], $insights['previous'] ?? []),
            ],
            'followersAudience' => $account->audience ?: null,
            'postsLast90Days' => PostStats::of($posts->map(fn (SocialPost $p) => self::post($p))->all(), $account->followers, $tz),
            'topPosts' => $withPosts ? $this->topPosts($posts, 5) : null,
            'syncedAt' => $account->synced_at?->setTimezone($tz)->format('Y-m-d H:i'),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Posts by engagement (likes + comments + shares + saves), best first.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(iterable $posts, int $limit): array
    {
        return collect($posts)
            ->sortByDesc(fn (SocialPost $p) => (int) $p->likes + (int) $p->comments + (int) $p->shares + (int) $p->saves)
            ->take($limit)
            ->map(fn (SocialPost $p) => $this->postLine($p))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function postLine(SocialPost $post): array
    {
        return array_filter([
            'postedAt' => $post->posted_at?->setTimezone($this->timezone())->format('Y-m-d H:i'),
            'kind' => $post->kind,
            'caption' => $post->caption ? Str::limit($post->caption, 300) : null,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'saves' => $post->saves,
            'reach' => $post->reach,
            'views' => $post->views,
            'permalink' => $post->permalink,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> */
    public function competitor(SocialCompetitor $competitor, bool $withPosts = false): array
    {
        return array_filter([
            'id' => $competitor->id,
            'username' => $competitor->username,
            'status' => $competitor->status,
            'error' => $competitor->error,
            'name' => $competitor->name,
            'picture' => $competitor->picture_url,
            'biography' => $withPosts ? $competitor->biography : null,
            'website' => $competitor->website,
            'url' => 'https://www.instagram.com/'.$competitor->username.'/',
            // Their ads: Meta's API gives other businesses' ads only in the EU,
            // so the public Ad Library page is the way to see them.
            'adLibraryUrl' => self::adLibraryUrl($competitor->name ?: $competitor->username),
            'followers' => $competitor->followers,
            'followersChange30Days' => $this->change(SocialCompetitorDay::class, 'social_competitor_id', $competitor->id, $competitor->followers, 30),
            'follows' => $competitor->follows,
            'mediaCount' => $competitor->media_count,
            'stats' => $competitor->stats ?: null,
            'recentPosts' => $withPosts ? $competitor->recent_posts : null,
            'syncedAt' => $competitor->synced_at?->setTimezone($this->timezone())->format('Y-m-d H:i'),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * The business's Instagram next to each watched account, by the same
     * public measures.
     *
     * @return list<array<string, mixed>>
     */
    public function comparison(): array
    {
        $own = $this->selectedAccounts()->first(fn (SocialAccount $a) => $a->isInstagram());
        $rows = [];

        if ($own) {
            $stats = PostStats::of($own->posts()->orderByDesc('posted_at')->limit(Competitors::POSTS)->get()->map(fn (SocialPost $p) => self::post($p))->all(), $own->followers, $this->timezone());
            $rows[] = ['own' => true, 'username' => $own->username, ...self::measures($own->followers, $own->media_count, $stats)];
        }

        foreach (SocialCompetitor::query()->where('status', SocialCompetitor::OK)->orderByDesc('followers')->get() as $competitor) {
            $rows[] = ['own' => false, 'username' => $competitor->username, ...self::measures($competitor->followers, $competitor->media_count, $competitor->stats ?? [])];
        }

        return $rows;
    }

    /**
     * What the assistant gets up front when the module is on: short, the
     * tools give the rest.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $connection = $this->connection();
        $competitors = SocialCompetitor::query()->orderBy('username')->get();

        if ($connection === null && $competitors->isEmpty()) {
            return ['connected' => false];
        }

        return array_filter([
            'connected' => $connection !== null && $connection->revoked_at === null,
            'needsReconnect' => $connection?->revoked_at !== null ? true : null,
            'lastSync' => $connection?->synced_at?->setTimezone($this->timezone())->format('Y-m-d H:i'),
            'accounts' => $this->selectedAccounts()->map(fn (SocialAccount $a) => collect($this->account($a))
                ->only(['network', 'handle', 'followers', 'followersChange30Days', 'mediaCount', 'last28Days'])
                ->all())->values()->all(),
            'watchedCompetitors' => $competitors->pluck('username')->all(),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public static function adLibraryUrl(string $query): string
    {
        return 'https://www.facebook.com/ads/library/?'.http_build_query([
            'active_status' => 'active',
            'ad_type' => 'all',
            'country' => 'ALL',
            'q' => $query,
            'search_type' => 'keyword_unordered',
        ]);
    }

    /** @return array<string, mixed> */
    public static function post(SocialPost $post): array
    {
        return [
            'kind' => $post->kind,
            'postedAt' => $post->posted_at,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'saves' => $post->saves,
            'reach' => $post->reach,
            'views' => $post->views,
        ];
    }

    /** @return array<string, mixed> */
    private static function measures(?int $followers, ?int $media, array $stats): array
    {
        return [
            'followers' => $followers,
            'mediaCount' => $media,
            'postsPerWeek' => $stats['postsPerWeek'] ?? null,
            'avgLikes' => $stats['avgLikes'] ?? null,
            'avgComments' => $stats['avgComments'] ?? null,
            'engagementRate' => $stats['engagementRate'] ?? null,
            'hiddenLikes' => $stats['hiddenLikes'] ?? false,
            'lastPostAt' => $stats['lastPostAt'] ?? null,
        ];
    }

    /**
     * Followers gained (or lost) over $days days, from the daily rows; null
     * until a row that old (or the first one after it, if newer) exists.
     *
     * @param  class-string<SocialAccountDay|SocialCompetitorDay>  $model
     */
    private function change(string $model, string $key, int $id, ?int $now, int $days): ?int
    {
        if ($now === null) {
            return null;
        }

        $today = CarbonImmutable::now($this->timezone())->startOfDay();
        $since = $today->subDays($days)->toDateString();

        $base = $model::query()->where($key, $id)->whereNotNull('followers')->where('date', '<=', $since)->orderByDesc('date')->first()
            ?? $model::query()->where($key, $id)->whereNotNull('followers')->where('date', '<', $today->toDateString())->orderBy('date')->first();

        return $base ? $now - $base->followers : null;
    }

    /**
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     * @return array<string, float>
     */
    private static function changes(array $current, array $previous): array
    {
        $changes = [];
        foreach ($current as $metric => $value) {
            $before = $previous[$metric] ?? null;
            if ($before) {
                $changes[$metric] = round(($value - $before) / $before * 100, 1);
            }
        }

        return $changes;
    }

    private function timezone(): string
    {
        return $this->tenants->current()->timezone ?: config('app.timezone');
    }
}
