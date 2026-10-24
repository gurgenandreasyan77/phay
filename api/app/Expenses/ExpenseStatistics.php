<?php

namespace App\Expenses;

use App\DataTables\DataColumn;
use App\DataTables\Decimal;
use App\DataTables\Period;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Models\DataTable;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the business spent, on its own, away from sales: for one day, week,
 * month, quarter or year (any date picked), how much in all, how it
 * compares with the period before, by category, by whom it was paid to,
 * by payment method, by who spent it, by object, the biggest expenses and
 * every expense of the period; with the periods before it for a chart and,
 * inside a longer period, its days or months.
 *
 * Rows of the expenses tables and recurring expenses on their due dates
 * (Finance::expenses), read by their columns' roles (ColumnRole::Supplier,
 * Payment, Performer, Project, Branch, Item, Tax...).
 */
final class ExpenseStatistics
{
    /** Periods before the chosen one shown on the chart, by kind. */
    private const SPANS = ['day' => 14, 'week' => 12, 'month' => 12, 'quarter' => 8, 'year' => 5];

    /** Breakdowns by the role of the expense column, with their Armenian titles. */
    private const BREAKDOWNS = [
        'supplier' => 'Ում է վճարվել',
        'payment' => 'Վճարման եղանակ',
        'performer' => 'Ով է ծախսել',
        'project' => 'Օբյեկտ',
        'branch' => 'Մասնաճյուղ',
        'item' => 'Ինչի համար',
        'status' => 'Կարգավիճակ',
    ];

    private const LIST = 300;

    public function __construct(
        private readonly Finance $finance,
        private readonly TenantContext $tenants,
    ) {}

    /** @return array<string, mixed> */
    public function report(TablePeriod $kind, ?string $date = null): array
    {
        $today = CarbonImmutable::now($this->tenants->current()->timezone ?: config('app.timezone'))->startOfDay();
        $period = Period::of($kind, $date ?? $today->toDateString());
        $first = $period->shift(-(self::SPANS[$kind->value] - 1));

        $categories = ExpenseCategories::all();
        $all = collect($this->finance->expenses($categories, $first->start, $period->end()));
        $tables = DataTable::query()->where('section', DataTableSection::Expenses)->get()->keyBy('id');
        $columns = $this->columns($tables);

        $in = fn (Period $p) => $all->filter(fn (array $e) => $p->contains($e['at']))->values();
        $current = $in($period);
        $previous = $in($period->shift(-1));

        // The periods up to the chosen one, for the chart.
        $series = [];
        for ($p = $first; $p->start <= $period->start; $p = $p->shift(1)) {
            $series[] = [...$p->toArray(), ...$this->sums($in($p))];
        }

        return [
            'by' => $kind->value,
            'today' => $today->toDateString(),
            'period' => $period->toArray(),
            'previous' => $period->shift(-1)->toArray(),
            // Rent due on the 25th is counted once it's due: a period not over yet isn't complete.
            'unfinished' => $period->end() >= $today,
            'totals' => [
                ...$this->sums($current),
                'previous' => $this->sums($previous)['total'],
                'perDay' => $this->perDay($current, $period, $today),
                'tax' => $this->roleSum($current, $columns, ColumnRole::Tax),
            ],
            'series' => $series,
            // Inside a week or a month its days, inside a quarter or a year its months.
            'inside' => $this->inside($current, $period),
            'categories' => array_values(array_filter($this->finance->byCategory($current, $categories), fn (array $c) => $c['amount'] > 0)),
            'previousCategories' => collect($this->finance->byCategory($previous, $categories))->filter(fn (array $c) => $c['amount'] > 0)->mapWithKeys(fn (array $c) => [$c['name'] => $c['amount']])->all(),
            'breakdowns' => $this->breakdowns($current, $columns),
            'largest' => $current->sortByDesc('amount')->take(10)->map(fn (array $e) => $this->line($e, $tables, $columns))->values()->all(),
            'rows' => $current->sortBy([['at', 'desc'], ['amount', 'desc']])->take(self::LIST)->map(fn (array $e) => $this->line($e, $tables, $columns))->values()->all(),
            'count' => $current->count(),
            'has' => [
                'tables' => $tables->isNotEmpty(),
                'recurring' => $all->contains(fn (array $e) => $e['recurring']),
                'roles' => collect($columns)->flatMap(fn (array $byRole) => array_keys($byRole))->unique()->values()->all(),
            ],
        ];
    }

    /**
     * Columns of the expenses tables by role: table id → role → key.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return array<int, array<string, string>>
     */
    private function columns(Collection $tables): array
    {
        return $tables->mapWithKeys(fn (DataTable $t) => [$t->id => collect($t->columnDefinitions())
            ->filter(fn (DataColumn $c) => $c->role !== null && ! $c->role->isPersonal())
            ->mapWithKeys(fn (DataColumn $c) => [$c->role->value => $c->key])
            ->all()])->all();
    }

    /** @return array{total: int|float, inProfit: int|float, excluded: int|float, recurring: int|float, oneOff: int|float, count: int} */
    private function sums(Collection $entries): array
    {
        $total = $entries->sum('amount');
        $excluded = $entries->where('inProfit', false)->sum('amount');
        $recurring = $entries->where('recurring', true)->sum('amount');

        return [
            'total' => Decimal::round($total),
            'inProfit' => Decimal::round($total - $excluded),
            'excluded' => Decimal::round($excluded),
            'recurring' => Decimal::round($recurring),
            'oneOff' => Decimal::round($total - $recurring),
            'count' => $entries->count(),
        ];
    }

    /** Spent a day on average, over the days of the period gone by. */
    private function perDay(Collection $entries, Period $period, CarbonImmutable $today): int|float|null
    {
        $end = min($period->end(), $today);
        if ($end < $period->start) {
            return null;
        }
        $days = (int) $period->start->diffInDays($end) + 1;

        return Decimal::round($entries->sum('amount') / $days);
    }

    /**
     * @param  array<int, array<string, string>>  $columns
     */
    private function roleSum(Collection $entries, array $columns, ColumnRole $role): int|float|null
    {
        $values = $entries->map(function (array $e) use ($columns, $role) {
            $key = $e['table'] ? ($columns[$e['table']][$role->value] ?? null) : null;
            $value = $key ? ($e['data'][$key] ?? null) : null;

            return is_int($value) || is_float($value) ? $value : null;
        })->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : Decimal::round($values->sum());
    }

    /** @return list<array{key: string, label: string, total: int|float, count: int}> */
    private function inside(Collection $entries, Period $period): array
    {
        $kind = match ($period->kind) {
            TablePeriod::Week, TablePeriod::Month => TablePeriod::Day,
            TablePeriod::Quarter, TablePeriod::Year => TablePeriod::Month,
            default => null,
        };
        if ($kind === null) {
            return [];
        }

        $points = [];
        for ($p = Period::of($kind, $period->start); $p->start <= $period->end(); $p = $p->shift(1)) {
            $points[$p->key()] = ['key' => $p->key(), 'start' => $p->start->toDateString(), 'label' => $p->label(), 'total' => 0, 'count' => 0];
        }
        foreach ($entries as $e) {
            $key = Period::of($kind, $e['at'])->key();
            if (isset($points[$key])) {
                $points[$key]['total'] += $e['amount'];
                $points[$key]['count']++;
            }
        }

        return array_values(array_map(fn (array $p) => [...$p, 'total' => Decimal::round($p['total'])], $points));
    }

    /**
     * Expenses grouped by the columns of each role (to whom, how, who...);
     * recurring expenses count under their own name as "what for".
     *
     * @param  array<int, array<string, string>>  $columns
     * @return list<array{role: string, label: string, values: list<array{value: string, amount: int|float, count: int}>}>
     */
    private function breakdowns(Collection $entries, array $columns): array
    {
        $result = [];

        foreach (self::BREAKDOWNS as $role => $label) {
            $groups = $entries
                ->map(function (array $e) use ($columns, $role) {
                    if ($e['table'] === null) {
                        return [...$e, 'value' => $role === 'item' ? $e['name'] : null];
                    }
                    $key = $columns[$e['table']][$role] ?? null;
                    $value = $key ? ($e['data'][$key] ?? null) : null;

                    return [...$e, 'value' => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null];
                })
                ->filter(fn (array $e) => $e['value'] !== null)
                ->groupBy('value');

            if ($groups->isEmpty()) {
                continue;
            }

            $result[] = [
                'role' => $role,
                'label' => $label,
                'distinct' => $groups->count(),
                'values' => $groups
                    ->map(fn (Collection $g, string $value) => ['value' => $value, 'amount' => Decimal::round($g->sum('amount')), 'count' => $g->count()])
                    ->sortByDesc('amount')
                    ->take(12)
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /**
     * One expense for the list: when, how much, its category and what its
     * columns say (what for, to whom, how, who), and where it comes from.
     *
     * @param  Collection<int, DataTable>  $tables
     * @param  array<int, array<string, string>>  $columns
     * @return array<string, mixed>
     */
    private function line(array $e, Collection $tables, array $columns): array
    {
        $value = function (string $role) use ($e, $columns) {
            $key = $e['table'] ? ($columns[$e['table']][$role] ?? null) : null;
            $v = $key ? ($e['data'][$key] ?? null) : null;

            return is_scalar($v) && trim((string) $v) !== '' ? (is_string($v) ? trim($v) : $v) : null;
        };

        return [
            'date' => $e['at'],
            'amount' => Decimal::round($e['amount']),
            'category' => $e['label'],
            'inProfit' => $e['inProfit'],
            'recurring' => $e['recurring'],
            'item' => $e['recurring'] ? $e['name'] : $value('item'),
            'supplier' => $value('supplier'),
            'payment' => $value('payment'),
            'performer' => $value('performer'),
            'project' => $value('project'),
            'note' => $value('note'),
            'tableId' => $e['table'],
            'table' => $e['table'] ? $tables->get($e['table'])?->name : null,
            'rowId' => $e['row'],
        ];
    }
}
