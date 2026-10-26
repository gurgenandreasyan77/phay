<?php

namespace App\Social;

use App\Models\SocialAccount;
use App\Models\SocialCompetitor;
use App\Models\SocialPost;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The assistant's look-ups about the business's Instagram and Facebook and
 * the accounts it watches. Only there when the super admin turned the
 * social module on for the business (BusinessTools asks). They read what
 * the last sync kept; only an account the business doesn't watch yet is
 * read from Meta, once, and kept for a while (Competitors::lookup).
 */
class SocialTools
{
    public const NAMES = ['social_profile', 'social_posts', 'social_competitors'];

    public function __construct(
        private readonly SocialReport $report,
        private readonly Competitors $competitors,
        private readonly TenantContext $tenants,
    ) {}

    public function available(): bool
    {
        return $this->report->enabled();
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $network = ['type' => 'string', 'enum' => [SocialAccount::INSTAGRAM, SocialAccount::FACEBOOK], 'description' => 'Only this network. Leave out for both.'];

        return [
            [
                'name' => 'social_profile',
                'description' => "The business's own connected Instagram and Facebook accounts: followers and their change over 7 and 30 days, totals of the last 28 days against the 28 before (reach, views, accounts engaged, interactions, likes, comments, shares, saves, profile link taps; for a Facebook Page its post engagements, views and follows), who the followers are (age, gender, city, country), and how its posts of the last 90 days do (posts a week, average likes and comments, engagement rate, best kind of post, best weekdays and hours) with the top posts. Use it for any question about the business's own social media.",
                'inputSchema' => ['type' => 'object', 'properties' => ['network' => $network], 'additionalProperties' => false],
            ],
            [
                'name' => 'social_posts',
                'description' => "The business's own posts on Instagram and Facebook with their likes, comments, shares, saves, reach and views, over any dates, sorted by date or by how well they did. Use it for questions about specific posts, what to post, or what worked.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'network' => $network,
                        'from' => ['type' => 'string', 'description' => 'First day, YYYY-MM-DD.'],
                        'to' => ['type' => 'string', 'description' => 'Last day, YYYY-MM-DD.'],
                        'kind' => ['type' => 'string', 'description' => 'IMAGE, VIDEO, CAROUSEL_ALBUM, REELS or post (Facebook).'],
                        'search' => ['type' => 'string', 'description' => 'Words the caption must contain.'],
                        'sort' => ['type' => 'string', 'enum' => ['newest', 'engagement', 'reach'], 'description' => 'newest (default), the most likes + comments + shares + saves, or the widest reach.'],
                        'limit' => ['type' => 'integer', 'description' => '1-50 (default 20).'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'social_competitors',
                'description' => "Competitors' public Instagram figures: followers and their change, posts, posts a week, average likes and comments, engagement rate, best kinds of post and times, compared with the business's own Instagram by the same measures. Without a username: every account the business watches and the comparison. With a username: that account's details and latest posts (an account not on the list is read once from Instagram; only business or creator accounts can be read, and only what they show publicly). Each has a link to its ads in Meta's public Ad Library.",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['username' => ['type' => 'string', 'description' => 'An Instagram username, e.g. "cleanhome.am".']],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException on a bad input
     */
    public function run(string $name, array $input): array
    {
        if (! $this->available()) {
            throw new InvalidArgumentException('The social media module is off for this business.');
        }

        return match ($name) {
            'social_profile' => $this->profile($input),
            'social_posts' => $this->posts($input),
            'social_competitors' => $this->competitorsOf($input),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };
    }

    /** @return array<string, mixed> */
    private function profile(array $input): array
    {
        $connection = $this->report->connection();
        $accounts = $this->accounts($input);

        if ($accounts->isEmpty()) {
            return ['connected' => false, 'note' => $connection
                ? 'The Facebook login is connected but no Page or Instagram account is chosen on the social media screen.'
                : 'No Instagram or Facebook account is connected yet: the owner connects it on the social media screen («Սոցցանցեր»).'];
        }

        return array_filter([
            'needsReconnect' => $connection?->revoked_at !== null ? 'Meta stopped accepting the access: figures are from the last sync; the owner should connect again.' : null,
            'accounts' => $accounts->map(fn (SocialAccount $a) => $this->report->account($a, withPosts: true))->values()->all(),
            'notes' => [
                'Insights totals are estimates by Meta and can be up to 48 hours late.',
                'Engagement rate = (likes + comments) per post / followers × 100.',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function posts(array $input): array
    {
        $accounts = $this->accounts($input);
        if ($accounts->isEmpty()) {
            return ['posts' => [], 'note' => 'No Instagram or Facebook account is connected.'];
        }

        $tz = $this->tenants->current()->timezone ?: config('app.timezone');
        $from = self::date($input['from'] ?? null);
        $to = self::date($input['to'] ?? null);
        $limit = max(1, min(50, (int) ($input['limit'] ?? 20)));
        $search = trim((string) ($input['search'] ?? ''));

        $query = SocialPost::query()
            ->with('account')
            ->whereIn('social_account_id', $accounts->pluck('id'))
            ->when($from, fn ($q) => $q->where('posted_at', '>=', CarbonImmutable::parse($from, $tz)->startOfDay()->utc()))
            ->when($to, fn ($q) => $q->where('posted_at', '<=', CarbonImmutable::parse($to, $tz)->endOfDay()->utc()))
            ->when(filled($input['kind'] ?? null), fn ($q) => $q->where('kind', Str::upper((string) $input['kind']) === 'POST' ? 'post' : Str::upper((string) $input['kind'])))
            ->when($search !== '', fn ($q) => $q->whereRaw('LOWER(caption) LIKE ?', ['%'.mb_strtolower($search).'%']));

        $matched = (clone $query)->count();
        $posts = (match ($input['sort'] ?? 'newest') {
            'engagement' => $query->orderByRaw('(COALESCE(likes,0) + COALESCE(comments,0) + COALESCE(shares,0) + COALESCE(saves,0)) DESC'),
            'reach' => $query->orderByRaw('COALESCE(reach, views, 0) DESC'),
            default => $query->orderByDesc('posted_at'),
        })->limit($limit)->get();

        return [
            'matched' => $matched,
            'posts' => $posts->map(fn (SocialPost $p) => ['account' => $p->account->handle(), 'network' => $p->account->network, ...$this->report->postLine($p)])->all(),
            'note' => 'Posts kept are the latest '.SocialSync::POSTS.' of each account at each sync, and every older one read before.',
        ];
    }

    /** @return array<string, mixed> */
    private function competitorsOf(array $input): array
    {
        if (filled($input['username'] ?? null)) {
            $username = Competitors::username((string) $input['username'])
                ?? throw new InvalidArgumentException('Not an Instagram username.');
            $watched = SocialCompetitor::query()->where('username', $username)->first();

            return [
                'account' => $watched ? $this->report->competitor($watched, withPosts: true) : [
                    ...$this->competitors->lookup($username),
                    'watched' => false,
                    'adLibraryUrl' => SocialReport::adLibraryUrl($username),
                ],
                'own' => collect($this->report->comparison())->firstWhere('own', true),
            ];
        }

        $watched = SocialCompetitor::query()->orderBy('username')->get();

        return [
            'watched' => $watched->map(fn (SocialCompetitor $c) => $this->report->competitor($c))->values()->all(),
            'comparison' => $this->report->comparison(),
            'notes' => array_values(array_filter([
                $watched->isEmpty() ? 'The business watches no competitors yet: they can be added by Instagram username on the social media screen, or asked about by username.' : null,
                'Only public figures of business or creator accounts: no reach, views or followers\' demographics of other accounts.',
                'Engagement rate = (likes + comments) per post / followers × 100; accounts that hide likes count comments only.',
            ])),
        ];
    }

    /** @return Collection<int, SocialAccount> */
    private function accounts(array $input)
    {
        $network = $input['network'] ?? null;

        return $this->report->selectedAccounts()
            ->filter(fn (SocialAccount $a) => ! $network || $a->network === $network)
            ->values();
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}
