<?php

namespace App\Expenses;

use App\DataTables\Decimal;
use App\DataTables\Period;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Enums\LeadStatus;
use App\Enums\TablePeriod;
use App\Features\FeatureFlag;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\ExpenseCategory;
use App\Models\Lead;
use App\Models\RecurringExpense;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;

/**
 * What the business earned and what was left, per day, week, month,
 * quarter or year:
 *
 *   revenue        orders and sales tables (amount) + completed site orders (final price)
 *   − cost of goods    unit cost × quantity of the rows that have one (ColumnRole::Cost)
 *   = gross profit
 *   − expenses     rows of the expenses tables and recurring expenses, of the
 *                  categories counted in the profit (ExpenseCategory::in_profit)
 *   = net profit
 *   − the rest of the expenses (equipment, a loan repayment, the owner's withdrawal...)
 *   = cash left
 *
 * Every row counts in the period its date falls in (occurred_at, the
 * business's local time), a recurring expense on each of its due dates.
 */
final class Finance
{
    /** How many periods a view shows, the chosen one the last. */
    public const SPANS = ['day' => 31, 'week' => 12, 'month' => 12, 'quarter' => 8, 'year' => 5];

    /** Names of a category-less expense and of the site's orders. */
    private const NO_CATEGORY = 'Առանց տեսակի';

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Periods of a kind up to the one a date falls in (today by default).
     *
     * @return array<string, mixed>
     */
    public function report(TablePeriod $kind, ?string $date = null, ?int $count = null): array
    {
        $today = $this->today();
        $last = Period::of($kind, $date ?? $today->toDateString());
        $periods = [];
        for ($i = ($count ?? self::SPANS[$kind->value]) - 1; $i >= 0; $i--) {
            $periods[] = $last->shift(-$i);
        }
        $from = $periods[0]->start;
        $to = $last->end();

        $categories = ExpenseCategories::all();
        $revenue = $this->revenue($from, $to);
        $expenses = $this->expenses($categories, $from, $to);

        $points = [];
        foreach ($periods as $period) {
            $points[$period->key()] = [...$period->toArray(), 'revenue' => 0, 'cogs' => 0, 'expenses' => 0, 'excluded' => 0, 'expenseRows' => 0];
        }
        $bucket = fn (string $at) => Period::of($kind, $at)->key();

        foreach ($revenue['rows'] as $row) {
            if (isset($points[$k = $bucket($row['at'])])) {
                $points[$k]['revenue'] += $row['amount'];
                $points[$k]['cogs'] += $row['cogs'];
            }
        }
        foreach ($expenses as $row) {
            if (isset($points[$k = $bucket($row['at'])])) {
                $points[$k][$row['inProfit'] ? 'expenses' : 'excluded'] += $row['amount'];
                $points[$k]['expenseRows']++;
            }
        }

        $points = array_values(array_map(fn (array $p) => $this->totals($p), $points));
        $current = end($points);
        $inCurrent = collect($expenses)->filter(fn ($r) => $last->contains($r['at']));

        return [
            'by' => $kind->value,
            'today' => $today->toDateString(),
            'periods' => $points,
            'current' => [
                ...$current,
                'previous' => count($points) > 1 ? $points[count($points) - 2] : null,
                'categories' => $this->byCategory($inCurrent, $categories),
            ],
            'total' => $this->totals([
                'revenue' => array_sum(array_column($points, 'revenue')),
                'cogs' => array_sum(array_column($points, 'cogs')),
                'expenses' => array_sum(array_column($points, 'expenses')),
                'excluded' => array_sum(array_column($points, 'excluded')),
                'expenseRows' => array_sum(array_column($points, 'expenseRows')),
            ]),
            'categories' => $this->byCategory(collect($expenses), $categories),
            'has' => [
                'revenue' => $revenue['tables'] > 0 || $revenue['site'],
                'cost' => $revenue['withCost'],
                'expenses' => DataTable::query()->where('section', DataTableSection::Expenses)->exists() || RecurringExpense::query()->exists(),
            ],
            'notes' => $this->notes($revenue, $expenses, $categories),
        ];
    }

    /**
     * @param  array<string, mixed>  $p  revenue, cogs, expenses, excluded of a period
     * @return array<string, mixed> with gross and net profit, cash left and margin
     */
    private function totals(array $p): array
    {
        $gross = $p['revenue'] - $p['cogs'];
        $net = $gross - $p['expenses'];

        return [
            ...$p,
            'revenue' => Decimal::round($p['revenue']),
            'cogs' => Decimal::round($p['cogs']),
            'expenses' => Decimal::round($p['expenses']),
            'excluded' => Decimal::round($p['excluded']),
            'grossProfit' => Decimal::round($gross),
            'netProfit' => Decimal::round($net),
            'cashLeft' => Decimal::round($net - $p['excluded']),
            'margin' => $p['revenue'] > 0 ? round($net / $p['revenue'] * 100, 1) : null,
        ];
    }

    /**
     * Money in: rows of the orders and sales tables (modules that are on)
     * and the site's completed orders, each with its cost of goods.
     *
     * @return array{rows: list<array{at: string, amount: int|float, cogs: int|float}>, tables: int, site: bool, withCost: bool, priced: int, costed: int}
     */
    private function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tables = DataTable::query()
            ->whereIn('section', collect(DataTableSection::revenue())->filter->isActive()->map->value->all())
            ->get();
        $costs = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => [
            'cost' => $t->roleColumn(ColumnRole::Cost)?->key,
            'quantity' => $t->quantityColumn()?->key,
        ]]);
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;
        $rows = [];
        $priced = 0;
        $costed = 0;

        DataTableRow::query()
            ->whereIn('data_table_id', $tables->pluck('id'))
            ->whereNotNull('amount')
            ->where('occurred_at', '>=', $from->format('Y-m-d 00:00:00'))
            ->where('occurred_at', '<=', $to->format('Y-m-d 23:59:59'))
            ->select(['id', 'data_table_id', 'occurred_at', 'amount', 'data'])
            ->chunkById(2000, function ($chunk) use (&$rows, &$priced, &$costed, $costs, $number) {
                foreach ($chunk as $row) {
                    $key = $costs[$row->data_table_id];
                    $data = $row->data ?? [];
                    $cost = $key['cost'] ? $number($data[$key['cost']] ?? null) : null;
                    $priced++;
                    if ($cost !== null) {
                        $costed++;
                    }
                    $rows[] = [
                        'at' => substr((string) $row->getRawOriginal('occurred_at'), 0, 10),
                        'amount' => (float) $row->amount,
                        'cogs' => $cost === null ? 0 : Decimal::multiply($cost, ($key['quantity'] ? $number($data[$key['quantity']] ?? null) : null) ?? 1),
                    ];
                }
            });

        $site = Feature::active(FeatureFlag::Orders->value);
        if ($site) {
            $timezone = $this->timezone();
            Lead::query()
                ->where('status', LeadStatus::Completed)
                ->whereNotNull('final_price')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $from->shiftTimezone($timezone)->utc())
                ->where('completed_at', '<=', $to->endOfDay()->shiftTimezone($timezone)->utc())
                ->get(['final_price', 'completed_at'])
                ->each(function (Lead $lead) use (&$rows, $timezone) {
                    $rows[] = [
                        'at' => CarbonImmutable::parse($lead->completed_at)->setTimezone($timezone)->toDateString(),
                        'amount' => (float) $lead->final_price,
                        'cogs' => 0,
                    ];
                });
            $site = Lead::query()->exists();
        }

        return [
            'rows' => $rows,
            'tables' => $tables->count(),
            'site' => $site,
            'withCost' => $tables->contains(fn (DataTable $t) => $t->roleColumn(ColumnRole::Cost) !== null),
            'priced' => $priced,
            'costed' => $costed,
        ];
    }

    /**
     * Money out: rows of the expenses tables and the due dates of the
     * recurring expenses, each with its category.
     *
     * @param  Collection<int, ExpenseCategory>  $categories
     * @return list<array{at: string, amount: int|float, category: ?ExpenseCategory, label: string, inProfit: bool, recurring: bool}>
     */
    private function expenses(Collection $categories, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $match = ExpenseCategories::matcher($categories);
        $byId = $categories->keyBy('id');
        $tables = DataTable::query()->where('section', DataTableSection::Expenses)->get();
        $keys = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => $t->roleColumn(ColumnRole::Category)?->key]);
        $rows = [];
        $entry = fn (string $at, int|float $amount, ?ExpenseCategory $category, ?string $raw, bool $recurring) => [
            'at' => $at,
            'amount' => $amount,
            'category' => $category,
            // A value matching no category is shown as it was written.
            'label' => $category?->name ?? ($raw !== null && trim($raw) !== '' ? trim($raw) : self::NO_CATEGORY),
            'inProfit' => $category?->in_profit ?? true,
            'recurring' => $recurring,
        ];

        DataTableRow::query()
            ->whereIn('data_table_id', $tables->pluck('id'))
            ->whereNotNull('amount')
            ->where('occurred_at', '>=', $from->format('Y-m-d 00:00:00'))
            ->where('occurred_at', '<=', $to->format('Y-m-d 23:59:59'))
            ->select(['id', 'data_table_id', 'occurred_at', 'amount', 'data'])
            ->chunkById(2000, function ($chunk) use (&$rows, $keys, $match, $entry) {
                foreach ($chunk as $row) {
                    $key = $keys[$row->data_table_id];
                    $raw = $key ? ($row->data[$key] ?? null) : null;
                    $raw = is_scalar($raw) ? (string) $raw : null;
                    $rows[] = $entry(substr((string) $row->getRawOriginal('occurred_at'), 0, 10), (float) $row->amount, $match($raw), $raw, false);
                }
            });

        // A recurring expense counts once it's due: rent due on the 25th isn't spent on the 19th.
        $until = CarbonImmutable::parse(min($to->toDateString(), $this->today()->toDateString()));
        foreach (RecurringExpense::query()->where('starts_on', '<=', $until->toDateString())->get() as $expense) {
            $category = $expense->expense_category_id ? $byId->get($expense->expense_category_id) : null;
            foreach ($expense->dueDates($from, $until) as $date) {
                $rows[] = $entry($date->toDateString(), $expense->value(), $category, null, true);
            }
        }

        return $rows;
    }

    /**
     * Expenses by category, the biggest first; every category the business
     * has is listed, the ones without expenses at the end.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, ExpenseCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function byCategory(Collection $rows, Collection $categories): array
    {
        $groups = $rows->groupBy(fn (array $r) => $r['category'] ? 'id:'.$r['category']->id : 'name:'.$r['label']);

        $listed = $categories->map(fn (ExpenseCategory $c) => $this->categoryLine($c->id, $c->key, $c->name, $c->in_profit, $groups->get('id:'.$c->id, collect())));
        $other = $groups->filter(fn ($g, string $k) => str_starts_with($k, 'name:'))
            ->map(fn (Collection $g) => $this->categoryLine(null, null, $g->first()['label'], true, $g));

        return $listed->concat($other->values())
            ->sortByDesc(fn (array $c) => [$c['amount'] > 0 ? 1 : 0, $c['amount']])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function categoryLine(?int $id, ?string $key, string $name, bool $inProfit, Collection $rows): array
    {
        return [
            'id' => $id,
            'key' => $key,
            'name' => $name,
            'inProfit' => $inProfit,
            'amount' => Decimal::round($rows->sum('amount')),
            'recurring' => Decimal::round($rows->where('recurring', true)->sum('amount')),
            'count' => $rows->count(),
        ];
    }

    /**
     * What the owner should know to trust the figures.
     *
     * @return list<string>
     */
    private function notes(array $revenue, array $expenses, Collection $categories): array
    {
        $notes = [];

        if ($revenue['withCost'] && $revenue['costed'] < $revenue['priced']) {
            $notes[] = "Ինքնարժեքը լրացված է {$revenue['priced']} վաճառքից {$revenue['costed']}-ի համար. մնացածի ինքնարժեքը հաշվված է 0։";
        }

        $purchases = $categories->firstWhere('key', 'purchases');
        if ($revenue['withCost'] && $purchases?->in_profit && collect($expenses)->contains(fn ($r) => $r['category']?->id === $purchases->id)) {
            $notes[] = "«{$purchases->name}» ծախսերը հանվում են շահույթից, իսկ վաճառքների ինքնարժեքն էլ է հանվում. եթե դրանք նույն ապրանքներն են, շահույթում դրանք երկու անգամ են հաշվված։ Անջատեք «Հաշվել շահույթում» այդ տեսակի համար կամ մի լրացրեք ինքնարժեքը։";
        }

        $unknown = collect($expenses)->filter(fn ($r) => $r['category'] === null)->pluck('label')->unique()->reject(fn ($l) => $l === self::NO_CATEGORY);
        if ($unknown->isNotEmpty()) {
            $notes[] = 'Այս տեսակները չկան ծախսերի տեսակների ցանկում և հաշվված են շահույթում՝ '.$unknown->take(5)->map(fn ($l) => "«{$l}»")->implode(', ').'։ Ավելացրեք դրանք ցանկում, եթե պետք է անջատել։';
        }

        return $notes;
    }

    private function timezone(): string
    {
        return $this->tenants->current()->timezone ?: config('app.timezone');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }
}
