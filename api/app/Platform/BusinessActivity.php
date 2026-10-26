<?php

namespace App\Platform;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * How active a business is, without its data: the days its own people
 * opened the admin (business_activity_days, one row per day, written once
 * a day per business) and when they were last seen.
 */
class BusinessActivity
{
    /** Days shown as a strip on the business's platform page. */
    public const RECENT_DAYS = 30;

    /** Called on every admin request of the business's own people; writes once a day. */
    public function record(Tenant $tenant): void
    {
        $day = $this->today($tenant)->toDateString();

        if (! Cache::add("business-activity:{$tenant->id}:{$day}", true, now()->addDay())) {
            return;
        }

        DB::table('business_activity_days')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'day' => $day,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function summary(Tenant $tenant): array
    {
        $today = $this->today($tenant);
        $from = $today->subDays(self::RECENT_DAYS - 1);

        $days = DB::table('business_activity_days')->where('tenant_id', $tenant->id);
        $recent = (clone $days)->where('day', '>=', $from->toDateString())->pluck('day')
            ->map(fn ($day) => substr((string) $day, 0, 10))
            ->flip();

        $first = (clone $days)->min('day');
        $last = (clone $days)->max('day');

        return [
            'createdAt' => $tenant->created_at?->toIso8601String(),
            // Days since the business was created, the first counted.
            'daysOnPlatform' => $tenant->created_at
                ? (int) $tenant->created_at->copy()->startOfDay()->diffInDays($today) + 1
                : null,
            'activeDays' => (clone $days)->count(),
            'activeDaysRecent' => $recent->count(),
            'firstActiveDay' => $first ? substr((string) $first, 0, 10) : null,
            'lastActiveDay' => $last ? substr((string) $last, 0, 10) : null,
            'lastSeenAt' => $this->lastSeenAt($tenant),
            // Oldest first: whether anyone worked that day.
            'recent' => collect(range(0, self::RECENT_DAYS - 1))
                ->map(fn (int $i) => $from->addDays($i)->toDateString())
                ->map(fn (string $day) => ['date' => $day, 'active' => $recent->has($day)])
                ->all(),
        ];
    }

    /** The latest use of a session of the business's own people. */
    private function lastSeenAt(Tenant $tenant): ?string
    {
        $members = $tenant->users()
            ->where('users.is_super_admin', false)
            ->where('users.is_platform_moderator', false)
            ->pluck('users.id');

        if ($members->isEmpty()) {
            return null;
        }

        $at = DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $members)
            ->max('last_used_at');

        return $at ? CarbonImmutable::parse($at)->toIso8601String() : null;
    }

    private function today(Tenant $tenant): CarbonImmutable
    {
        return CarbonImmutable::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
    }
}
