<?php

namespace App\Assistant;

use App\DataTables\DataColumn;
use App\DataTables\Decimal;
use App\DataTables\PeriodTables;
use App\DataTables\TableAnalytics;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Expenses\ExpenseStatistics;
use App\Expenses\Finance;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Lead;
use App\Social\SocialTools;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What the AI assistant can look up on its own while it answers: the rows
 * of the business's tables for any dates or words, sums of them grouped by
 * day, month, product, performer..., money in and out for any month or
 * year, and the orders from the site. The snapshot in the prompt covers the
 * usual questions; these answer the precise ones ("how much did we sell
 * on 3 March", "which product brought most in summer").
 *
 * Everything runs inside the current business (tenant-scoped models), and
 * columns about buyers (names, phones, addresses) never leave: they are
 * neither returned nor searched. With the social module on, the business's
 * Instagram and Facebook and its competitors' public figures too
 * (SocialTools).
 */
class BusinessTools implements ToolSet
{
    /** Rows read at most for one question: plenty for a small business. */
    private const MAX_ROWS = 20000;

    private const MAX_LIST = 100;

    private const MAX_GROUPS = 40;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Finance $finance,
        private readonly ExpenseStatistics $expenses,
        private readonly SocialTools $social,
        private readonly TableActionTools $actions,
    ) {}

    /**
     * Tool definitions for the Messages API (SDK keys: inputSchema).
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $section = [
            'type' => 'string',
            'enum' => array_column(DataTableSection::cases(), 'value'),
            'description' => 'orders (services the business does), sales (goods it sells) or expenses. Leave out for orders and sales together.',
        ];
        $date = fn (string $what) => ['type' => 'string', 'description' => "{$what}, YYYY-MM-DD, in the business's time zone."];

        return [
            ...$this->ownDefinitions($section, $date),
            // Only for a business with the social module on.
            ...($this->social->available() ? $this->social->definitions() : []),
            // Only for those who design the tables (owner, admin): cards the owner acts on.
            ...($this->actions->available() ? $this->actions->definitions() : []),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function ownDefinitions(array $section, callable $date): array
    {
        return [
            [
                'name' => 'list_tables',
                'description' => "Lists the business's own tables: section, period, columns (label, type and what the column means, e.g. date, item, model, brand, country, supplier, intermediary, quantity, unit_price, discount, amount, cost, commission, performer, channel, category...), rows and first/last date, and what each meaning stands for (\"meanings\"). Call it first when you need exact column labels for group_rows or find_rows.",
                'inputSchema' => ['type' => 'object'],
            ],
            [
                'name' => 'find_rows',
                'description' => 'Finds rows of the business\'s own tables (orders, sales, expenses) by dates and words, with how many matched and their total amount. Use it for questions about specific days, products, services, performers or channels. Buyer names and contacts are never included.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => $section,
                        'from' => $date('First day, inclusive'),
                        'to' => $date('Last day, inclusive'),
                        'search' => ['type' => 'string', 'description' => 'Words a row must contain in any of its columns (case-insensitive), e.g. a product or a performer.'],
                        'table' => ['type' => 'string', 'description' => 'Part of a table name, to look in some tables only.'],
                        'sort' => ['type' => 'string', 'enum' => ['newest', 'oldest', 'amount'], 'description' => 'newest (default), oldest, or the biggest amount first.'],
                        'limit' => ['type' => 'integer', 'description' => 'Rows to return, 1-100 (default 30). The totals always cover every matching row.'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'group_rows',
                'description' => 'Sums rows of the business\'s own tables over a period, grouped by day, week, month, weekday, hour or by a column (product, service, model, brand, supplier, country, intermediary, performer, channel, payment, status, category, city... by its label or meaning). Gives rows, amount, quantity, discounts and commissions and, where unit cost or commission is known, profit per group. For expenses use section "expenses" (rows of the expenses tables only; expenses_report also counts recurring expenses).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => $section,
                        'from' => $date('First day, inclusive'),
                        'to' => $date('Last day, inclusive'),
                        'by' => ['type' => 'string', 'description' => 'day, week, month, weekday, hour, or a column: its label (e.g. "Ապրանք") or its meaning ('.implode(', ', array_map(fn (ColumnRole $r) => $r->value, self::groupable())).').'],
                        'search' => ['type' => 'string', 'description' => 'Only rows that contain these words.'],
                    ],
                    'required' => ['from', 'to', 'by'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'finance_report',
                'description' => 'Money in and out per period up to the one a date falls in: revenue, cost of goods, gross profit, expenses (by category), net profit, cash left and margin. Use it for profit and expense questions about any week, month, quarter or year.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'by' => ['type' => 'string', 'enum' => ['week', 'month', 'quarter', 'year']],
                        'date' => $date('A day inside the last period wanted (default today)'),
                        'count' => ['type' => 'integer', 'description' => 'How many periods back, 1-24 (default: 12 months, 8 quarters, 3 years or 12 weeks).'],
                    ],
                    'required' => ['by'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'expenses_report',
                'description' => 'What the business spent in one day, week, month, quarter or year (the one a date falls in), apart from sales: total, the period before, in and out of profit, recurring vs one-off, per day, by category, by payee, payment method, who spent, object and what for, the biggest expenses, its days or months, and the expenses themselves (up to 50). Counts recurring expenses on their due dates too. Use it for any question about spending.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'by' => ['type' => 'string', 'enum' => ['day', 'week', 'month', 'quarter', 'year']],
                        'date' => $date('A day inside the period (default today)'),
                    ],
                    'required' => ['by'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'site_orders',
                'description' => 'Orders that came from the business\'s website over a period: how many, by status, service and source, revenue of the completed ones and the orders themselves (without client names or contacts).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'from' => $date('First day the order was made, inclusive'),
                        'to' => $date('Last day, inclusive'),
                        'status' => ['type' => 'string', 'description' => 'new, contacted, estimated, confirmed, in_progress, completed or cancelled.'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Runs a tool for the model; the answer goes back as JSON.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException on a bad tool or input
     */
    public function run(string $name, array $input): array
    {
        return match ($name) {
            'list_tables' => $this->listTables(),
            'find_rows' => $this->findRows($input),
            'group_rows' => $this->groupRows($input),
            'finance_report' => $this->financeReport($input),
            'expenses_report' => $this->expensesReport($input),
            'site_orders' => $this->siteOrders($input),
            'social_profile', 'social_posts', 'social_competitors' => $this->social->run($name, $input),
            TableActionTools::RESHAPE, TableActionTools::DOWNLOAD => $this->actions->run($name, $input),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };
    }

    /** @return array<string, mixed> */
    private function listTables(): array
    {
        $tables = PeriodTables::infer(DataTable::query()->orderBy('section')->orderBy('sort_order')->orderBy('id')->get());
        $totals = DataTableRow::query()
            ->groupBy('data_table_id')
            ->selectRaw('data_table_id, COUNT(*) as total, MIN(occurred_at) as first_at, MAX(occurred_at) as last_at')
            ->get()
            ->keyBy('data_table_id');

        $used = $tables->flatMap(fn (DataTable $t) => collect($t->columnDefinitions())->pluck('role')->filter())->unique(fn (ColumnRole $r) => $r->value);

        return [
            'meanings' => $used->reject(fn (ColumnRole $r) => $r->isPersonal())->mapWithKeys(fn (ColumnRole $r) => [$r->value => $r->meaning()])->all(),
            'tables' => $tables->take(80)->map(fn (DataTable $t) => array_filter([
                'id' => $t->id,
                'name' => $t->name,
                'section' => $t->section->value,
                'period' => $t->period?->value,
                // The period it holds: "Հոկտեմբեր 2026", "2026" (also read from the name of an older table).
                'periodLabel' => $t->period()?->label(),
                'columns' => collect($t->columnDefinitions())
                    ->reject(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c))
                    ->map(fn (DataColumn $c) => array_filter(['label' => $c->label, 'type' => $c->type->value, 'meaning' => $c->role?->value]))
                    ->values()
                    ->all(),
                'rows' => (int) ($totals[$t->id]->total ?? 0),
                'firstDate' => isset($totals[$t->id]) ? substr((string) $totals[$t->id]->first_at, 0, 10) ?: null : null,
                'lastDate' => isset($totals[$t->id]) ? substr((string) $totals[$t->id]->last_at, 0, 10) ?: null : null,
            ], fn ($v) => $v !== null && $v !== ''))->values()->all(),
        ];
    }

    /** @return list<ColumnRole> meanings a row can be grouped by */
    private static function groupable(): array
    {
        return array_values(array_filter(ColumnRole::cases(), fn (ColumnRole $r) => ! $r->isPersonal() && ! in_array($r, [ColumnRole::Date, ColumnRole::Time, ColumnRole::Note, ColumnRole::OrderNumber, ColumnRole::Quantity, ColumnRole::UnitPrice, ColumnRole::Amount, ColumnRole::Cost, ColumnRole::Duration, ColumnRole::DiscountPercent, ...ColumnRole::extraMoney()], true)));
    }

    /** @return array<string, mixed> */
    private function findRows(array $input): array
    {
        $tables = $this->tablesOf($input);
        $rows = $this->rows($tables, $input);
        $limit = max(1, min(self::MAX_LIST, (int) ($input['limit'] ?? 30)));

        $sorted = match ($input['sort'] ?? 'newest') {
            'oldest' => $rows->sortBy(fn ($r) => $r['at'] ?? '9999'),
            'amount' => $rows->sortByDesc(fn ($r) => $r['amount'] ?? -INF),
            default => $rows->sortByDesc(fn ($r) => $r['at'] ?? ''),
        };

        return [
            'matched' => $rows->count(),
            'amount' => $this->sum($rows),
            'quantity' => $this->quantities($rows),
            'shown' => min($limit, $rows->count()),
            'rows' => $sorted->take($limit)->map(fn ($r) => array_filter([
                'table' => $r['tableName'],
                'section' => $r['section'],
                'at' => $r['at'],
                'amount' => $r['amount'],
                'values' => $r['values'],
            ], fn ($v) => $v !== null && $v !== []))->values()->all(),
            'truncated' => $rows->count() >= self::MAX_ROWS,
        ];
    }

    /** @return array<string, mixed> */
    private function groupRows(array $input): array
    {
        $this->date($input['from'] ?? null) ?? throw new InvalidArgumentException('from is required (YYYY-MM-DD).');
        $this->date($input['to'] ?? null) ?? throw new InvalidArgumentException('to is required (YYYY-MM-DD).');
        $by = trim((string) ($input['by'] ?? ''));
        $tables = $this->tablesOf($input);
        $rows = $this->rows($tables, $input)->filter(fn ($r) => $r['at'] !== null);

        $time = match (mb_strtolower($by)) {
            'day' => fn ($r) => substr($r['at'], 0, 10),
            'week' => fn ($r) => CarbonImmutable::parse($r['at'])->startOfWeek()->toDateString(),
            'month' => fn ($r) => substr($r['at'], 0, 7),
            'weekday' => fn ($r) => CarbonImmutable::parse($r['at'])->isoWeekday().' '.CarbonImmutable::parse($r['at'])->englishDayOfWeek,
            'hour' => fn ($r) => substr($r['at'], 11, 2) ?: null,
            default => null,
        };

        if ($time) {
            $groups = $rows->groupBy(fn ($r) => (string) $time($r))->sortKeys();
            $label = $by;
        } else {
            [$label, $keys] = $this->columnFor($tables, $by);
            $groups = $rows
                ->map(fn ($r) => [...$r, 'group' => isset($keys[$r['table']]) ? $this->text($r['data'][$keys[$r['table']]] ?? null) : null])
                ->filter(fn ($r) => $r['group'] !== null)
                ->groupBy('group')
                ->sortByDesc(fn (Collection $g) => [$this->sum($g) ?? 0, $g->count()]);
        }

        $withCost = $rows->contains(fn ($r) => $r['cost'] !== null || $r['commission'] !== null);

        return [
            'by' => $label,
            'period' => ['from' => $input['from'], 'to' => $input['to']],
            'rows' => $rows->count(),
            'amount' => $this->sum($rows),
            'groups' => $groups->take(self::MAX_GROUPS)->map(fn (Collection $g, string $key) => array_filter([
                'group' => $key,
                'rows' => $g->count(),
                'amount' => $this->sum($g),
                'quantity' => $this->quantities($g),
                'discount' => $this->total($g, 'discount'),
                'commission' => $this->total($g, 'commission'),
                'profit' => $withCost ? $this->profit($g) : null,
            ], fn ($v) => $v !== null && $v !== []))->values()->all(),
            'otherGroups' => max(0, $groups->count() - self::MAX_GROUPS),
        ];
    }

    /** @return array<string, mixed> */
    private function financeReport(array $input): array
    {
        $kind = TablePeriod::tryFrom((string) ($input['by'] ?? ''));
        if (! in_array($kind, [TablePeriod::Week, TablePeriod::Month, TablePeriod::Quarter, TablePeriod::Year], true)) {
            throw new InvalidArgumentException('by must be week, month, quarter or year.');
        }
        $count = isset($input['count']) ? max(1, min(24, (int) $input['count'])) : null;

        $report = $this->finance->report($kind, $this->date($input['date'] ?? null), $count);
        $line = fn (array $p) => collect($p)->only(['key', 'label', 'start', 'end', 'revenue', 'cogs', 'grossProfit', 'expenses', 'excluded', 'netProfit', 'cashLeft', 'margin'])->all();
        $spent = fn (array $categories) => collect($categories)->filter(fn ($c) => $c['amount'] > 0)->map(fn ($c) => collect($c)->only(['name', 'inProfit', 'amount', 'recurring'])->all())->values()->all();

        return [
            'periods' => array_map($line, $report['periods']),
            'total' => $line($report['total']),
            'categories' => $spent($report['categories']),
            'lastPeriodCategories' => $spent($report['current']['categories']),
            'has' => $report['has'],
            'notes' => $report['notes'],
        ];
    }

    /** @return array<string, mixed> */
    private function expensesReport(array $input): array
    {
        $kind = TablePeriod::tryFrom((string) ($input['by'] ?? ''));
        if (! $kind || $kind === TablePeriod::Custom) {
            throw new InvalidArgumentException('by must be day, week, month, quarter or year.');
        }

        $report = $this->expenses->report($kind, $this->date($input['date'] ?? null));

        return [
            ...collect($report)->only(['period', 'previous', 'unfinished', 'totals', 'inside', 'breakdowns', 'largest', 'count'])->all(),
            'categories' => array_map(fn (array $c) => collect($c)->only(['name', 'inProfit', 'amount', 'recurring', 'count'])->all(), $report['categories']),
            'previousCategories' => $report['previousCategories'],
            'expenses' => array_slice($report['rows'], 0, 50),
        ];
    }

    /** @return array<string, mixed> */
    private function siteOrders(array $input): array
    {
        $tz = $this->timezone();
        $from = $this->date($input['from'] ?? null);
        $to = $this->date($input['to'] ?? null);

        $leads = Lead::query()->with('service')
            ->when($from, fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($from, $tz)->startOfDay()->utc()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($to, $tz)->endOfDay()->utc()))
            ->when(filled($input['status'] ?? null), fn ($q) => $q->where('status', $input['status']))
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get();

        $completed = $leads->filter(fn (Lead $l) => $l->status->value === 'completed');

        return [
            'orders' => $leads->count(),
            'byStatus' => $leads->countBy(fn (Lead $l) => $l->status->value)->all(),
            'byService' => $leads->countBy(fn (Lead $l) => $l->service?->trans('name', 'hy') ?? '—')->sortDesc()->take(20)->all(),
            'bySource' => $leads->countBy(fn (Lead $l) => $l->utm_source ?: ($l->source ?: '—'))->sortDesc()->take(20)->all(),
            'completedRevenue' => Decimal::round($completed->sum(fn (Lead $l) => (float) $l->final_price)),
            'latest' => $leads->take(50)->map(fn (Lead $l) => array_filter([
                'date' => $l->created_at?->setTimezone($tz)->toDateString(),
                'service' => $l->service?->trans('name', 'hy'),
                'status' => $l->status->value,
                'source' => $l->utm_source ?: $l->source,
                'preferredDate' => $l->preferred_date?->toDateString(),
                'estimate' => $l->estimated_price_min || $l->estimated_price_max
                    ? trim(($l->estimated_price_min ?? '').'-'.($l->estimated_price_max ?? ''), '-')
                    : null,
                'finalPrice' => $l->final_price,
                'completedAt' => $l->completed_at?->setTimezone($tz)->toDateString(),
            ], fn ($v) => $v !== null && $v !== ''))->values()->all(),
        ];
    }

    /** @return Collection<int, DataTable> */
    private function tablesOf(array $input): Collection
    {
        $section = DataTableSection::tryFrom((string) ($input['section'] ?? ''));
        $sections = $section ? [$section] : DataTableSection::revenue();
        $name = mb_strtolower(trim((string) ($input['table'] ?? '')));

        return DataTable::query()
            ->whereIn('section', array_map(fn (DataTableSection $s) => $s->value, $sections))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->filter(fn (DataTable $t) => $name === '' || str_contains(mb_strtolower($t->name), $name))
            ->values();
    }

    /**
     * Rows of the tables in the period that contain the search words, each
     * with its non-personal values by column label.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $tables, array $input): Collection
    {
        if ($tables->isEmpty()) {
            return collect();
        }

        $from = $this->date($input['from'] ?? null);
        $to = $this->date($input['to'] ?? null);
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) ($input['search'] ?? '')))) ?: []));
        $byId = $tables->keyBy('id');
        $columns = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => collect($t->columnDefinitions())
            ->reject(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c))
            ->values()
            ->all()]);

        return DataTableRow::query()
            ->whereIn('data_table_id', $tables->pluck('id'))
            // occurred_at is the business's local time, like the dates asked.
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get(['id', 'data_table_id', 'occurred_at', 'amount', 'data'])
            ->map(function (DataTableRow $row) use ($byId, $columns) {
                $table = $byId[$row->data_table_id];
                $data = $row->data ?? [];
                $values = [];
                foreach ($columns[$table->id] as $column) {
                    $value = $data[$column->key] ?? null;
                    if ($value !== null && $value !== '' && $column->role !== ColumnRole::Date) {
                        $values[$column->label] = $value;
                    }
                }
                $quantity = $table->quantityColumn();
                $unit = $table->roleColumn(ColumnRole::Unit);
                $cost = $table->roleColumn(ColumnRole::Cost);
                $commission = $table->roleColumn(ColumnRole::Commission);
                $discount = $table->roleColumn(ColumnRole::Discount);
                $at = $row->getRawOriginal('occurred_at');

                return [
                    'table' => $table->id,
                    'tableName' => $table->name,
                    'section' => $table->section->value,
                    'at' => $at ? substr((string) $at, 0, 16) : null,
                    'amount' => $row->amount,
                    'values' => $values,
                    'data' => $data,
                    'quantity' => $quantity ? $this->number($data[$quantity->key] ?? null) : null,
                    'unit' => $unit ? $this->text($data[$unit->key] ?? null) : null,
                    'cost' => $cost ? $this->number($data[$cost->key] ?? null) : null,
                    'commission' => $commission ? $this->number($data[$commission->key] ?? null) : null,
                    'discount' => $discount ? $this->number($data[$discount->key] ?? null) : null,
                ];
            })
            ->filter(function (array $r) use ($words) {
                if ($words === []) {
                    return true;
                }
                $haystack = mb_strtolower($r['tableName'].' '.implode(' ', array_map(
                    fn ($v) => is_bool($v) ? ($v ? 'այո yes' : 'ոչ no') : (string) $v,
                    $r['values'],
                )));
                foreach ($words as $word) {
                    if (! str_contains($haystack, $word)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * The column to group by, across tables: by its meaning (item,
     * performer...) or by its label.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return array{0: string, 1: array<int, string>} label, key per table id
     */
    private function columnFor(Collection $tables, string $by): array
    {
        $role = ColumnRole::tryFrom(mb_strtolower($by));
        $wanted = mb_strtolower($by);
        $keys = [];
        $label = $by;

        foreach ($tables as $table) {
            foreach ($table->columnDefinitions() as $column) {
                if (TableAnalytics::isPersonalColumn($column)) {
                    continue;
                }
                $matches = $role ? $column->role === $role : mb_strtolower(trim($column->label)) === $wanted;
                if ($matches && ! isset($keys[$table->id])) {
                    $keys[$table->id] = $column->key;
                    $label = $column->label;
                }
            }
        }

        if ($keys === []) {
            throw new InvalidArgumentException("No column \"{$by}\" in these tables (columns about buyers can't be used). Call list_tables for the labels.");
        }

        return [$label, $keys];
    }

    private function total(Collection $rows, string $field): int|float|null
    {
        $filled = $rows->whereNotNull($field);

        return $filled->isEmpty() ? null : Decimal::round($filled->sum($field));
    }

    private function sum(Collection $rows): int|float|null
    {
        $priced = $rows->whereNotNull('amount');

        return $priced->isEmpty() ? null : Decimal::round($priced->sum('amount'));
    }

    /** @return array<string, int|float> quantity per unit ("" when the table has no unit) */
    private function quantities(Collection $rows): array
    {
        return $rows->whereNotNull('quantity')
            ->groupBy(fn ($r) => $r['unit'] ?? 'units')
            ->map(fn (Collection $g) => Decimal::round($g->sum('quantity')))
            ->all();
    }

    private function profit(Collection $rows): int|float|null
    {
        $costed = $rows->filter(fn ($r) => ($r['cost'] !== null || $r['commission'] !== null) && $r['amount'] !== null);

        return $costed->isEmpty() ? null : Decimal::round($costed->sum(fn ($r) => $r['amount'] - TableAnalytics::costOfSale($r)));
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }

    private function number(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
    }

    private function text(mixed $value): ?string
    {
        return match (true) {
            $value === true => 'Այո',
            $value === false => 'Ոչ',
            is_scalar($value) && trim((string) $value) !== '' => trim((string) $value),
            default => null,
        };
    }

    private function timezone(): string
    {
        return $this->tenants->current()->timezone ?: config('app.timezone');
    }
}
