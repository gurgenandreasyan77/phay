<?php

namespace App\Audience;

use App\DataTables\TableAnalytics;
use App\Enums\ColumnRole;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Lead;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Who buys from the business and where they come from, over a period:
 * orders and sales by channel (the tables' channel column, site orders as
 * «Կայք»), by gender and by age group (the tables' gender and age columns),
 * and the connected social accounts. Only shares and sums, never a row:
 * gender and age are about people.
 */
class Audience
{
    /** Rows of a period read at most, the latest ones (as TableAnalytics). */
    private const MAX_ROWS = 20000;

    public const AGE_GROUPS = ['<18', '18-24', '25-34', '35-44', '45-54', '55+'];

    public function __construct(
        private readonly TableAnalytics $analytics,
        private readonly TenantContext $tenants,
        private readonly SocialSource $social,
    ) {}

    /** @return array<string, mixed> */
    public function summary(int $days): array
    {
        $tenant = $this->tenants->current();
        $now = CarbonImmutable::now($tenant->timezone ?: config('app.timezone'));
        $start = $now->startOfDay()->subDays($days - 1);
        $tables = $this->analytics->tables();

        $keys = fn (ColumnRole $role) => $tables
            ->mapWithKeys(fn (DataTable $t) => [$t->id => $t->roleColumn($role)?->key])
            ->filter();
        $channel = $keys(ColumnRole::Channel);
        $gender = $keys(ColumnRole::Gender);
        $age = $keys(ColumnRole::Age);
        $ids = $channel->keys()->merge($gender->keys())->merge($age->keys())->unique()->values()->all();

        $rows = $ids === [] ? collect() : DataTableRow::query()->counted()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<=', $now->endOfDay()->format('Y-m-d H:i:s'))
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get(['data_table_id', 'amount', 'data']);

        $value = fn (Collection $keys) => fn (DataTableRow $r) => isset($keys[$r->data_table_id]) ? (($r->data ?? [])[$keys[$r->data_table_id]] ?? null) : null;

        // Site orders count under their UTM source when it names one, «Կայք» otherwise.
        $siteOrders = Lead::query()
            ->where('source', 'website')
            ->where('created_at', '>=', $start->utc())
            ->get(['utm_source', 'final_price'])
            ->map(fn (Lead $lead) => ['label' => self::channel($lead->utm_source) ?? 'Կայք', 'amount' => $lead->final_price]);

        return [
            'period' => ['days' => $days, 'from' => $start->toDateString(), 'to' => $now->toDateString()],
            'channels' => $channel->isEmpty() && $siteOrders->isEmpty() ? null : $this->channels($rows, $value($channel), $siteOrders),
            'gender' => $gender->isEmpty() ? null : $this->groups($rows, $value($gender), self::gender(...), ['female', 'male', 'other']),
            'age' => $age->isEmpty() ? null : $this->groups($rows, $value($age), self::ageGroup(...), self::AGE_GROUPS),
            'social' => $this->social->accounts($tenant, $days),
        ];
    }

    /** @return list<array{key: string, label: string, rows: int, amount: int|float|null}> */
    private function channels(Collection $rows, callable $value, Collection $siteOrders): array
    {
        return $rows
            ->map(fn (DataTableRow $r) => ['label' => self::channel($value($r)), 'amount' => $r->amount])
            ->concat($siteOrders)
            ->filter(fn (array $r) => $r['label'] !== null)
            ->groupBy('label')
            ->map(fn (Collection $g) => ['rows' => $g->count(), 'amount' => $g->whereNotNull('amount')->isEmpty() ? null : $g->sum('amount')])
            ->map(fn (array $g, string $label) => ['key' => mb_strtolower($label), 'label' => $label, 'rows' => $g['rows'], 'amount' => self::round($g['amount'])])
            ->sortByDesc('rows')
            ->values()
            ->all();
    }

    /**
     * Rows counted per group in the given order; empty groups stay, so the
     * screen always shows the same groups.
     *
     * @param  list<string>  $order
     * @return list<array{key: string, rows: int, amount: int|float|null}>
     */
    private function groups(Collection $rows, callable $value, callable $group, array $order): array
    {
        $counted = $rows
            ->map(fn (DataTableRow $r) => ['group' => $group($value($r)), 'amount' => $r->amount])
            ->filter(fn (array $r) => $r['group'] !== null)
            ->groupBy('group');

        return collect($order)
            ->map(fn (string $key) => [
                'key' => $key,
                'rows' => $counted->get($key)?->count() ?? 0,
                'amount' => self::round($counted->get($key)?->whereNotNull('amount')->sum('amount')),
            ])
            ->all();
    }

    /** "Իգական", "Ж", "female", "կին" → female; empty → null. */
    public static function gender(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = mb_strtolower(trim($value));

        return match (true) {
            (bool) preg_match('/^(իգ|կին|աղջ|ж|f|w|kin|ig)/u', $value) => 'female',
            (bool) preg_match('/^(արա|տղ|այր|м|m|ar|tx)/u', $value) => 'male',
            default => 'other',
        };
    }

    /** 34 → "25-34", "25-34" → "25-34", "60+" → "55+"; nothing readable → null. */
    public static function ageGroup(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/\d+/', $value, $match)) {
            $value = (int) $match[0];
        }

        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        return match (true) {
            $value <= 0 || $value > 120 => null,
            $value < 18 => '<18',
            $value < 25 => '18-24',
            $value < 35 => '25-34',
            $value < 45 => '35-44',
            $value < 55 => '45-54',
            default => '55+',
        };
    }

    /** "insta", "IG", "Ինստագրամ" → Instagram; the business's own words otherwise. */
    public static function channel(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $lower = mb_strtolower($value);

        return match (true) {
            (bool) preg_match('/insta|^ig$|ինստա|инста/u', $lower) => 'Instagram',
            (bool) preg_match('/facebook|^fb$|ֆեյսբուք|фейсбук/u', $lower) => 'Facebook',
            (bool) preg_match('/tik\s*tok|տիկտոկ|тикток/u', $lower) => 'TikTok',
            (bool) preg_match('/telegram|տելեգրամ|телеграм/u', $lower) => 'Telegram',
            (bool) preg_match('/whats\s*app|վաթսափ|ватсап/u', $lower) => 'WhatsApp',
            (bool) preg_match('/viber|վայբեր|вайбер/u', $lower) => 'Viber',
            (bool) preg_match('/կայք|site|web|сайт/u', $lower) => 'Կայք',
            default => $value,
        };
    }

    private static function round(int|float|null $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $rounded = round($value, 2);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}
