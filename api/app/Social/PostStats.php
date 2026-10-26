<?php

namespace App\Social;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a list of posts says about an account, the same way for the
 * business's own accounts and for the ones it watches, so they compare:
 * how often it posts, average likes and comments, engagement rate
 * ((likes + comments) per post / followers), which kind of post and which
 * weekday and hour work best (in the business's time zone).
 */
class PostStats
{
    private const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @param  iterable<array{kind?: ?string, postedAt?: mixed, likes?: ?int, comments?: ?int, shares?: ?int, saves?: ?int, reach?: ?int, views?: ?int}>  $posts
     * @return array<string, mixed>
     */
    public static function of(iterable $posts, ?int $followers, string $timezone, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now($timezone);
        $posts = collect($posts)
            ->map(fn (array $p) => [...$p, 'at' => self::time($p['postedAt'] ?? null, $timezone)])
            ->filter(fn (array $p) => $p['at'] !== null)
            ->sortByDesc('at')
            ->values();

        if ($posts->isEmpty()) {
            return ['posts' => 0];
        }

        $liked = $posts->filter(fn ($p) => ($p['likes'] ?? null) !== null);
        $engagement = fn (array $p) => (int) ($p['likes'] ?? 0) + (int) ($p['comments'] ?? 0);
        $avg = fn (Collection $c, string $field) => $c->whereNotNull($field)->isEmpty() ? null : round($c->whereNotNull($field)->avg($field), 1);
        $perPost = $posts->avg($engagement);
        $last30 = $posts->filter(fn ($p) => $p['at']->greaterThanOrEqualTo($now->subDays(30)))->count();
        $last90 = $posts->filter(fn ($p) => $p['at']->greaterThanOrEqualTo($now->subDays(90)))->count();
        $oldest = $posts->last()['at'];
        // Fewer posts than were read cover a shorter time: per week over what they cover.
        $weeks = max(1, $oldest->diffInDays($now) / 7);

        return array_filter([
            'posts' => $posts->count(),
            'from' => $oldest->toDateString(),
            'to' => $posts->first()['at']->toDateString(),
            'lastPostAt' => $posts->first()['at']->format('Y-m-d H:i'),
            'postsLast30Days' => $last30,
            'postsLast90Days' => $last90,
            'postsPerWeek' => round($posts->count() / $weeks, 1),
            'avgLikes' => $avg($posts, 'likes'),
            'avgComments' => $avg($posts, 'comments'),
            'avgShares' => $avg($posts, 'shares'),
            'avgSaves' => $avg($posts, 'saves'),
            'avgReach' => $avg($posts, 'reach'),
            'avgViews' => $avg($posts, 'views'),
            // Likes hidden by the owner: the rate counts comments only.
            'hiddenLikes' => $liked->count() < $posts->count() / 2,
            'engagementRate' => $followers ? round($perPost / $followers * 100, 2) : null,
            'byKind' => $posts->groupBy(fn ($p) => $p['kind'] ?: 'OTHER')
                ->map(fn (Collection $g, string $kind) => array_filter([
                    'kind' => $kind,
                    'posts' => $g->count(),
                    'avgLikes' => $avg($g, 'likes'),
                    'avgComments' => $avg($g, 'comments'),
                    'avgReach' => $avg($g, 'reach'),
                    'avgViews' => $avg($g, 'views'),
                ], fn ($v) => $v !== null))
                ->sortByDesc('posts')->values()->all(),
            'bestWeekdays' => self::best($posts, fn ($p) => self::WEEKDAYS[$p['at']->isoWeekday()], $engagement),
            'bestHours' => self::best($posts, fn ($p) => $p['at']->format('H').':00', $engagement),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Groups with at least two posts, by average likes + comments, best first.
     *
     * @return list<array{group: string, posts: int, avgEngagement: float}>
     */
    private static function best(Collection $posts, callable $group, callable $engagement): array
    {
        return $posts->groupBy($group)
            ->filter(fn (Collection $g) => $g->count() >= 2)
            ->map(fn (Collection $g, string $key) => ['group' => $key, 'posts' => $g->count(), 'avgEngagement' => round($g->avg($engagement), 1)])
            ->sortByDesc('avgEngagement')
            ->take(3)
            ->values()
            ->all();
    }

    private static function time(mixed $value, string $timezone): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone($timezone);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone($timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
