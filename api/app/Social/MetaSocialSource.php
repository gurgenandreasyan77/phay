<?php

namespace App\Social;

use App\Audience\SocialSource;
use App\Models\SocialAccount;
use App\Models\SocialAccountDay;
use App\Models\SocialPost;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The connected Instagram and Facebook accounts on the analytics screen
 * («Սոցիալական ցանցեր»): followers, their change over the period, reach of
 * the last 28 days and the engagement rate of the period's posts. Nothing
 * while the social module is off or nothing is connected.
 */
class MetaSocialSource implements SocialSource
{
    public function __construct(private readonly SocialReport $report) {}

    public function accounts(Tenant $tenant, int $days): array
    {
        if (! $this->report->enabled()) {
            return [];
        }

        $today = CarbonImmutable::now($tenant->timezone ?: config('app.timezone'))->startOfDay();

        return $this->report->selectedAccounts()
            ->filter(fn (SocialAccount $a) => $a->followers !== null)
            ->map(function (SocialAccount $account) use ($today, $days) {
                $base = SocialAccountDay::query()->where('social_account_id', $account->id)->whereNotNull('followers')
                    ->where('date', '>=', $today->subDays($days)->toDateString())->orderBy('date')->first();
                $posts = SocialPost::query()->where('social_account_id', $account->id)
                    ->where('posted_at', '>=', $today->subDays($days)->utc())->get();
                $perPost = $posts->isEmpty() ? 0 : $posts->avg(fn (SocialPost $p) => (int) $p->likes + (int) $p->comments);
                $insights = $account->insights['current'] ?? [];

                return [
                    'network' => $account->network,
                    'handle' => $account->handle(),
                    'followers' => (int) $account->followers,
                    'followersChange' => $base ? (int) $account->followers - (int) $base->followers : 0,
                    'reach' => (int) ($insights['reach'] ?? $insights['page_total_media_view_unique'] ?? 0),
                    'engagement' => $account->followers ? round($perPost / $account->followers * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();
    }
}
