<?php

namespace App\Analytics;

use App\DataTables\Decimal;
use App\Enums\BusinessRole;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\LeadStatus;
use App\Enums\TablePeriod;
use App\Expenses\Finance;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Lead;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * The business admin's home: the few numbers a business looks at first,
 * read from where its work is actually kept.
 *
 *   sections   orders and sales tables (the ones the business keeps: orders
 *              alone while it has no tables yet): rows and money
 *              today, this week, this month so far, and the same days of
 *              last month to compare with
 *   finance    this month so far: revenue, expenses, net profit (App\Expenses\Finance)
 *   upcoming   orders of the next days: rows of the order tables and the
 *              site's orders by the date the customer asked for
 *   site       the site's orders waiting for an answer or in work (only
 *              for a business that has them)
 *
 * Money is only for roles that see revenue (BusinessRole::INSIGHTS), net
 * profit also needs the expenses (BusinessRole::EXPENSES); the rest is
 * counts, which everyone who works with orders sees in the tables anyway.
 * Rows are placed by occurred_at, the business's local time.
 */
final class Home
{
    /** How far ahead "upcoming" looks, today included. */
    public const UPCOMING_DAYS = 7;

    public const UPCOMING_LIMIT = 8;

    public const RECENT_LEADS = 5;

    /** Site orders nobody has agreed on yet. */
    private const WAITING = [LeadStatus::New, LeadStatus::Contacted, LeadStatus::WaitingForPhotos, LeadStatus::Estimated];

    private const IN_WORK = [LeadStatus::Confirmed, LeadStatus::InProgress];

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Finance $finance,
    ) {}

    /** @return array<string, mixed> */
    public function for(?User $user): array
    {
        $role = $user?->businessRole($this->tenants->current());
        $money = (bool) $role?->can(BusinessRole::INSIGHTS);
        $today = $this->today();

        return [
            'today' => $today->toDateString(),
            'money' => $money,
            'sections' => $this->sections($today, $money),
            'finance' => $money && $role->can(BusinessRole::EXPENSES) ? $this->finance($today) : null,
            'upcoming' => $this->upcoming($today, $money),
            'site' => Lead::query()->exists() ? $this->site($today, $money) : null,
        ];
    }

    /**
     * Each revenue section that is on, with its tables: what came in today,
     * this week, this month so far, and the same days of last month.
     *
     * @return list<array<string, mixed>>
     */
    private function sections(CarbonImmutable $today, bool $money): array
    {
        $ranges = $this->ranges($today);
        $sections = [];

        foreach (DataTableSection::revenue() as $section) {
            $tables = DataTable::query()->where('section', $section)->get();
            $hasAmount = $money && $tables->contains(fn (DataTable $t) => $t->amountColumn() !== null);
            $ids = $tables->modelKeys();

            $figures = [];
            foreach ($ranges as $key => [$from, $to]) {
                $figures[$key] = $this->figures($ids, $from, $to, $hasAmount);
            }

            $last = $ids === [] ? null : DataTableRow::query()->whereIn('data_table_id', $ids)->whereNotNull('occurred_at')
                ->where('occurred_at', '<', $today->addDay()->format('Y-m-d H:i:s'))
                ->max('occurred_at');

            $sections[] = [
                'section' => $section->value,
                'tables' => count($ids),
                'rows' => $ids === [] ? 0 : DataTableRow::query()->counted()->whereIn('data_table_id', $ids)->count(),
                'hasAmount' => $hasAmount,
                ...$figures,
                'lastAt' => $last ? substr((string) $last, 0, 16) : null,
            ];
        }

        // Every business has both sections; a shop that keeps only sales
        // shouldn't see an empty orders card, a new business sees orders.
        $kept = array_values(array_filter($sections, fn (array $s) => $s['tables'] > 0));

        return $kept === [] ? array_slice($sections, 0, 1) : $kept;
    }

    /**
     * Today, this week, this month so far and the same days of last month
     * (the 1st to the 19th against the 1st to the 19th), each [from, to] by day.
     *
     * @return array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function ranges(CarbonImmutable $today): array
    {
        $previousStart = $today->startOfMonth()->subMonthNoOverflow();

        return [
            'today' => [$today, $today],
            'week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'month' => [$today->startOfMonth(), $today],
            'previousMonth' => [$previousStart, min($previousStart->addDays($today->day - 1), $previousStart->endOfMonth()->startOfDay())],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{count: int, amount: int|float|null}
     */
    private function figures(array $ids, CarbonImmutable $from, CarbonImmutable $to, bool $withAmount): array
    {
        if ($ids === []) {
            return ['count' => 0, 'amount' => $withAmount ? 0 : null];
        }

        $row = DataTableRow::query()->counted()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $from->format('Y-m-d 00:00:00'))
            ->where('occurred_at', '<=', $to->format('Y-m-d 23:59:59'))
            ->selectRaw('COUNT(*) as rows_count, SUM(amount) as amount_sum')
            ->first();

        return [
            'count' => (int) ($row->rows_count ?? 0),
            'amount' => $withAmount ? Decimal::round((float) ($row->amount_sum ?? 0)) : null,
        ];
    }

    /**
     * This month so far and the same days of last month: revenue, expenses
     * and net profit, as on the expenses screen (day by day, summed).
     *
     * @return array<string, mixed>
     */
    private function finance(CarbonImmutable $today): array
    {
        [, , [$monthFrom], [$previousFrom, $previousTo]] = array_values($this->ranges($today));
        $current = $this->finance->report(TablePeriod::Day, $today->toDateString(), (int) $monthFrom->diffInDays($today) + 1);
        $previous = $this->finance->report(TablePeriod::Day, $previousTo->toDateString(), (int) $previousFrom->diffInDays($previousTo) + 1);
        $pick = fn (array $report) => [
            'revenue' => $report['total']['revenue'],
            'cogs' => $report['total']['cogs'],
            'expenses' => $report['total']['expenses'],
            'netProfit' => $report['total']['netProfit'],
            'cashLeft' => $report['total']['cashLeft'],
            'margin' => $report['total']['margin'],
        ];

        return [
            ...$pick($current),
            'previous' => $pick($previous),
            'has' => $current['has'],
        ];
    }

    /**
     * Orders from today on: rows of the order tables with a date column and
     * site orders still to do, by the day the customer asked for.
     *
     * @return array{items: list<array<string, mixed>>, total: int, days: int}
     */
    private function upcoming(CarbonImmutable $today, bool $money): array
    {
        $until = $today->addDays(self::UPCOMING_DAYS);
        $tables = DataTable::query()
            ->where('section', DataTableSection::Orders)
            ->get()
            ->filter(fn (DataTable $t) => $t->dateColumn() !== null)
            ->keyBy('id');

        $rowsQuery = DataTableRow::query()->counted()
            ->whereIn('data_table_id', $tables->keys()->all())
            ->where('occurred_at', '>=', $today->format('Y-m-d 00:00:00'))
            ->where('occurred_at', '<', $until->format('Y-m-d 00:00:00'));
        $rowsTotal = (clone $rowsQuery)->count();
        $rows = $rowsQuery->orderBy('occurred_at')->orderBy('id')->limit(self::UPCOMING_LIMIT)
            ->get(['id', 'data_table_id', 'data', 'occurred_at', 'amount']);

        $leadsQuery = Lead::query()
            ->whereNotNull('preferred_date')
            ->whereNotIn('status', [LeadStatus::Completed, LeadStatus::Cancelled])
            ->where('preferred_date', '>=', $today->toDateString())
            ->where('preferred_date', '<', $until->toDateString());
        $leadsTotal = (clone $leadsQuery)->count();
        $leads = $leadsQuery->with('service')->orderBy('preferred_date')->orderBy('id')->limit(self::UPCOMING_LIMIT)->get();

        $items = $rows->map(fn (DataTableRow $row) => $this->rowItem($tables[$row->data_table_id], $row, $money))
            ->concat($leads->map(fn (Lead $lead) => [
                'kind' => 'site',
                'id' => $lead->id,
                'date' => $lead->preferred_date->toDateString(),
                'time' => null,
                'title' => $lead->service?->trans('name', 'hy') ?? 'Պատվեր',
                'customer' => $lead->name,
                'performer' => null,
                'amount' => $money && $lead->final_price !== null ? (float) $lead->final_price : null,
                'status' => $lead->status->value,
            ]))
            ->sortBy(fn (array $item) => $item['date'].' '.($item['time'] ?? '00:00'))
            ->take(self::UPCOMING_LIMIT)
            ->values()
            ->all();

        return ['items' => $items, 'total' => $rowsTotal + $leadsTotal, 'days' => self::UPCOMING_DAYS];
    }

    /** @return array<string, mixed> */
    private function rowItem(DataTable $table, DataTableRow $row, bool $money): array
    {
        $at = CarbonImmutable::parse($row->getRawOriginal('occurred_at'));
        $text = function (ColumnRole $role) use ($table, $row): ?string {
            $column = $table->roleColumn($role);
            $value = $column ? ($row->data[$column->key] ?? null) : null;

            return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        };

        return [
            'kind' => 'row',
            'id' => $row->id,
            'tableId' => $table->id,
            'table' => $table->name,
            'date' => $at->toDateString(),
            // Only a date-and-time column has a time.
            'time' => $table->dateColumn()->type === DataColumnType::DateTime ? $at->format('H:i') : null,
            'title' => $text(ColumnRole::Item) ?? $table->name,
            'customer' => $text(ColumnRole::Customer),
            'performer' => $text(ColumnRole::Performer),
            'amount' => $money && $row->amount !== null ? (float) $row->amount : null,
            'status' => $text(ColumnRole::Status),
        ];
    }

    /**
     * The site's orders: new today, waiting for an answer, in work, done
     * this month, and the latest few.
     *
     * @return array<string, mixed>
     */
    private function site(CarbonImmutable $today, bool $money): array
    {
        // $today is the business's local midnight; leads keep UTC.
        $utc = fn (CarbonImmutable $local) => $local->utc();
        $monthStart = $utc($today->startOfMonth());
        $completed = Lead::query()->where('status', LeadStatus::Completed)->where('completed_at', '>=', $monthStart);

        return [
            'newToday' => Lead::query()->where('created_at', '>=', $utc($today))->count(),
            'waiting' => Lead::query()->whereIn('status', self::WAITING)->count(),
            'inWork' => Lead::query()->whereIn('status', self::IN_WORK)->count(),
            'completedMonth' => (clone $completed)->count(),
            'revenueMonth' => $money ? Decimal::round((float) (clone $completed)->sum('final_price')) : null,
            'recent' => Lead::query()->with('service')->latest('created_at')->latest('id')->limit(self::RECENT_LEADS)->get(),
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tenants->current()->timezone ?: config('app.timezone'))->startOfDay();
    }
}
