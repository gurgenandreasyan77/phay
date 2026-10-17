<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sales figures of the current business, built from its orders (leads).
 *
 * An order counts in the period it was created; revenue counts in the period
 * an order was completed, from its final price. Rows are aggregated in PHP,
 * in the business's own timezone: a small business has at most a few
 * thousand orders a year, and this keeps SQLite (tests) and Postgres equal.
 */
class BusinessAnalytics
{
    public const PERIODS = [7, 30, 90, 365];

    private const OPEN_STATUSES = [
        LeadStatus::New,
        LeadStatus::Contacted,
        LeadStatus::WaitingForPhotos,
        LeadStatus::Estimated,
        LeadStatus::Confirmed,
        LeadStatus::InProgress,
    ];

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days): array
    {
        $timezone = $this->tenants->current()->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $start = $now->startOfDay()->subDays($days - 1);
        $previousStart = $start->subDays($days);

        $leads = Lead::query()
            ->where(fn ($q) => $q
                ->where('created_at', '>=', $previousStart->utc())
                ->orWhere('completed_at', '>=', $previousStart->utc()))
            ->get(['id', 'status', 'service_id', 'source', 'utm_source', 'final_price', 'created_at', 'completed_at'])
            ->map(fn (Lead $lead) => [
                'status' => $lead->status,
                'service_id' => $lead->service_id,
                'source' => $lead->utm_source ?: ($lead->source ?: 'unknown'),
                'final_price' => $lead->final_price,
                'created' => CarbonImmutable::parse($lead->created_at)->setTimezone($timezone),
                'completed' => $lead->completed_at ? CarbonImmutable::parse($lead->completed_at)->setTimezone($timezone) : null,
            ]);

        $current = $this->totals($leads, $start, $now);
        $previous = $this->totals($leads, $previousStart, $start);

        $created = $leads->filter(fn (array $l) => $l['created'] >= $start);
        $completed = $this->completedBetween($leads, $start, $now);

        return [
            'period' => [
                'days' => $days,
                'from' => $start->toDateString(),
                'to' => $now->toDateString(),
                'timezone' => $timezone,
                'currency' => $this->tenants->current()->currency ?: 'AMD',
            ],
            'totals' => $current,
            'previous' => $previous,
            'series' => $this->series($leads, $start, $now, $days > 90 ? 'month' : 'day'),
            'topServices' => $this->topServices($created, $completed),
            'sources' => $created
                ->groupBy('source')
                ->map(fn (Collection $group, string $source) => [
                    'source' => $source,
                    'orders' => $group->count(),
                    'completed' => $group->where('status', LeadStatus::Completed)->count(),
                ])
                ->sortByDesc('orders')
                ->values()
                ->all(),
            'statuses' => collect(LeadStatus::cases())
                ->mapWithKeys(fn (LeadStatus $s) => [$s->value => $created->where('status', $s)->count()])
                ->all(),
            'openOrders' => Lead::query()->whereIn('status', self::OPEN_STATUSES)->count(),
            // Orders ever: a business with none has no site orders to show.
            'everOrders' => Lead::query()->count(),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function totals(Collection $leads, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $created = $leads->filter(fn (array $l) => $l['created'] >= $from && $l['created'] < $to);
        $completed = $this->completedBetween($leads, $from, $to);
        $priced = $completed->whereNotNull('final_price');
        $revenue = (int) $priced->sum('final_price');
        $orders = $created->count();
        $completedOfCreated = $created->where('status', LeadStatus::Completed)->count();

        return [
            'orders' => $orders,
            'completed' => $completed->count(),
            'cancelled' => $created->where('status', LeadStatus::Cancelled)->count(),
            'revenue' => $revenue,
            'averageCheck' => $priced->isEmpty() ? null : (int) round($revenue / $priced->count()),
            'completedWithoutPrice' => $completed->whereNull('final_price')->count(),
            // Share of this period's orders that are already completed.
            'conversion' => $orders === 0 ? null : round($completedOfCreated / $orders * 100, 1),
        ];
    }

    private function completedBetween(Collection $leads, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $leads->filter(fn (array $l) => $l['status'] === LeadStatus::Completed
            && $l['completed'] !== null && $l['completed'] >= $from && $l['completed'] < $to);
    }

    /**
     * @return list<array{date: string, orders: int, revenue: int}>
     */
    private function series(Collection $leads, CarbonImmutable $from, CarbonImmutable $to, string $bucket): array
    {
        $format = $bucket === 'month' ? 'Y-m' : 'Y-m-d';
        $points = [];

        for ($cursor = $from; $cursor <= $to; $cursor = $bucket === 'month' ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay()) {
            $points[$cursor->format($format)] = ['date' => $cursor->format($format), 'orders' => 0, 'revenue' => 0];
        }

        foreach ($leads as $lead) {
            if ($lead['created'] >= $from && isset($points[$key = $lead['created']->format($format)])) {
                $points[$key]['orders']++;
            }
        }

        foreach ($this->completedBetween($leads, $from, $to) as $lead) {
            $points[$lead['completed']->format($format)]['revenue'] += (int) $lead['final_price'];
        }

        return array_values($points);
    }

    /**
     * @return list<array{serviceId: int|null, name: string|null, orders: int, revenue: int}>
     */
    private function topServices(Collection $created, Collection $completed): array
    {
        $ids = $created->pluck('service_id')->merge($completed->pluck('service_id'))->unique()->values();
        $names = Service::query()->whereIn('id', $ids->filter())->get()
            ->mapWithKeys(fn (Service $s) => [$s->id => $s->trans('name', 'hy')]);

        return $ids
            ->map(fn (?int $id) => [
                'serviceId' => $id,
                'name' => $id ? ($names[$id] ?? null) : null,
                'orders' => $created->where('service_id', $id)->count(),
                'revenue' => (int) $completed->where('service_id', $id)->sum('final_price'),
            ])
            ->sortByDesc(fn (array $row) => [$row['revenue'], $row['orders']])
            ->take(10)
            ->values()
            ->all();
    }
}
