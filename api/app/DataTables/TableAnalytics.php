<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Figures of a business's own tables, per section (orders, sales), over all
 * tables of the section together: a business that imports a file a day has
 * thirty tables a month, and wants one month of sales, not thirty.
 *
 * Rows are placed in time and valued by occurred_at and amount (see
 * RowMetrics), which are kept on each row when it's saved, so a period is
 * one indexed query. Column breakdowns join columns of the same role (price,
 * product...) or, without one, the same name across tables. Feeds the analytics screen and the AI assistant.
 */
class TableAnalytics
{
    /** Rows of a period read at most for breakdowns (the latest ones). */
    private const MAX_ROWS = 50000;

    private const TOP_VALUES = 10;

    private const MAX_BREAKDOWNS = 6;

    /** Columns about the buyer (contacts, names) stay out of AI prompts and breakdowns. */
    public const PERSONAL_LABELS = '/հեռախոս|phone|tel\b|телефон|e-?mail|էլ\.?\s*փոստ|почта|հասցե|address|адрес|հաճախորդ|գնորդ|client|customer|buyer|клиент|покупатель|заказчик|անուն\s*ազգանուն|фио|instagram|insta|facebook|telegram|whatsapp|viber|\bnick|\buser/iu';

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Tables of the sections that bring money in (orders, sales);
     * expenses are figured by App\Expenses\Finance.
     *
     * @return Collection<int, DataTable>
     */
    public function tables(): Collection
    {
        $sections = collect(DataTableSection::revenue())
            ->map(fn (DataTableSection $s) => $s->value);

        return DataTable::query()->whereIn('section', $sections)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, DataTable>  $tables  tables of the section
     * @return array<string, mixed>
     */
    public function section(DataTableSection $section, Collection $tables, int $days): array
    {
        [$now, $start, $previousStart, $end] = $this->bounds($days);
        $ids = $tables->pluck('id')->all();
        $read = $this->rowReader($tables);

        $current = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<=', $end->format('Y-m-d H:i:s'))
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get(['data_table_id', 'occurred_at', 'amount', 'data'])
            ->map($read);

        $previous = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $previousStart->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $start->format('Y-m-d H:i:s'))
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get(['data_table_id', 'occurred_at', 'amount', 'data'])
            ->map($read);

        $totals = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->groupBy('data_table_id')
            ->selectRaw('data_table_id, COUNT(*) as total, SUM(CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END) as without_date, MIN(occurred_at) as first_at, MAX(occurred_at) as last_at')
            ->get()
            ->keyBy('data_table_id');

        $hasAmount = $tables->contains(fn (DataTable $t) => $t->amountColumn() !== null);
        $priced = $current->whereNotNull('amount');
        $firstAt = $totals->pluck('first_at')->filter()->min();
        // occurred_at is the business's local time, like the bounds.
        $firstAt = $firstAt ? CarbonImmutable::parse(substr((string) $firstAt, 0, 10), $now->getTimezone()) : null;
        $series = $this->series($current, $start, $now, $days > 90 ? 'month' : 'day', $tables->contains(fn (DataTable $t) => $t->roleColumn(ColumnRole::Cost) !== null));

        return [
            'section' => $section->value,
            'period' => ['days' => $days, 'from' => $start->toDateString(), 'to' => $now->toDateString()],
            'rows' => [
                'total' => (int) $totals->sum('total'),
                // Rows with no date are in no period; the screen says how many.
                'withoutDate' => (int) $totals->sum('without_date'),
                'current' => $current->count(),
                'previous' => $previous->count(),
            ],
            'amount' => $hasAmount ? [
                'current' => $this->round($current->sum('amount')),
                'previous' => $this->round($previous->sum('amount')),
                'average' => $priced->isEmpty() ? null : $this->round($priced->avg('amount')),
            ] : null,
            'metrics' => $this->metrics($section, $tables, $current, $previous, $start, $previousStart, $now, $firstAt),
            'series' => $series,
            'peaks' => $this->peaks($series, $days > 90 ? 'month' : 'day', $hasAmount, $firstAt),
            // The last twelve months whatever the period: which months bring more.
            'months' => $this->months($ids, $now, $hasAmount, $firstAt),
            'weekdays' => $this->weekdays($current),
            'hours' => $this->hours($tables, $current),
            'breakdowns' => $this->breakdowns($tables, $current),
            'numbers' => $this->numbers($tables, $current),
            'tables' => $tables->map(function (DataTable $table) use ($current, $totals) {
                $rows = $current->where('table', $table->id);
                $total = $totals->get($table->id);

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'dateColumn' => $table->dateColumn()?->label,
                    'amountColumn' => $table->amountLabel(),
                    'rows' => $rows->count(),
                    'amount' => $table->amountColumn() ? $this->round($rows->sum('amount')) : null,
                    'total' => (int) ($total->total ?? 0),
                    'withoutDate' => (int) ($total->without_date ?? 0),
                    'firstAt' => $total?->first_at ? substr((string) $total->first_at, 0, 16) : null,
                    'lastAt' => $total?->last_at ? substr((string) $total->last_at, 0, 16) : null,
                ];
            })->sortByDesc('rows')->values()->all(),
        ];
    }

    /**
     * Reads a stored row into what the figures need: its table, time,
     * amount, data, and its quantity, unit and unit cost by their roles.
     *
     * @param  Collection<int, DataTable>  $tables
     */
    private function rowReader(Collection $tables): callable
    {
        $keys = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => [
            'quantity' => $t->quantityColumn()?->key,
            'unit' => $t->roleColumn(ColumnRole::Unit)?->key,
            'cost' => $t->roleColumn(ColumnRole::Cost)?->key,
        ]]);
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;

        return function (DataTableRow $r) use ($keys, $number) {
            $data = $r->data ?? [];
            $key = $keys[$r->data_table_id];
            $unit = $key['unit'] ? ($data[$key['unit']] ?? null) : null;

            return [
                'table' => $r->data_table_id,
                'at' => CarbonImmutable::parse($r->getRawOriginal('occurred_at')),
                'amount' => $r->amount,
                'data' => $data,
                'quantity' => $key['quantity'] ? $number($data[$key['quantity']] ?? null) : null,
                'unit' => is_string($unit) && trim($unit) !== '' ? trim($unit) : null,
                'cost' => $key['cost'] ? $number($data[$key['cost']] ?? null) : null,
            ];
        };
    }

    /**
     * The figures that make sense for what the business records, decided by
     * the roles of its columns: a service business with only dates gets
     * orders a day; a shop selling by weight also gets revenue, the average
     * check, kilograms sold, the average weight of a sale and the price of a
     * kilogram; a unit cost adds profit. No role, no figure.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array{key: string, label: string, format: string, value: int|float|null, previous: int|float|null, unit?: ?string, note?: string}>
     */
    private function metrics(DataTableSection $section, Collection $tables, Collection $current, Collection $previous, CarbonImmutable $start, CarbonImmutable $previousStart, CarbonImmutable $now, ?CarbonImmutable $firstAt): array
    {
        $orders = $section === DataTableSection::Orders;
        $hasAmount = $tables->contains(fn (DataTable $t) => $t->amountColumn() !== null);
        $hasCost = $tables->contains(fn (DataTable $t) => $t->roleColumn(ColumnRole::Cost) !== null);
        $hasQuantity = $tables->contains(fn (DataTable $t) => $t->quantityColumn() !== null);

        // Days the business could have rows in: a business that started
        // last week has a week, not a month, of orders to average.
        $days = fn (CarbonImmutable $from, CarbonImmutable $to) => $firstAt === null || $firstAt > $to
            ? 0
            : (int) max($firstAt, $from)->diffInDays($to) + 1;
        $currentDays = $days($start, $now);
        $previousDays = $days($previousStart, $start->subDay());
        $perDay = fn (int $rows, int $d) => $d === 0 ? null : $this->round($rows / $d);

        $metrics = [];

        if ($hasAmount) {
            $metrics[] = ['key' => 'revenue', 'label' => 'Եկամուտ', 'format' => 'money', 'value' => $this->round($current->sum('amount')), 'previous' => $this->round($previous->sum('amount'))];
        }

        if ($hasAmount && $hasCost) {
            [$profit, $covered, $revenue] = $this->profit($current);
            [$previousProfit, $previousCovered] = $this->profit($previous);
            $priced = $current->whereNotNull('amount')->count();
            $note = $covered > 0 && $covered < $priced ? "Հաշվված է {$priced} տողից {$covered}-ի համար, որոնց ինքնարժեքը լրացված է։" : null;

            if ($covered > 0) {
                $metrics[] = ['key' => 'profit', 'label' => 'Շահույթ', 'format' => 'money', 'value' => $profit, 'previous' => $previousCovered > 0 ? $previousProfit : null, 'note' => $note];
                $metrics[] = ['key' => 'margin', 'label' => 'Շահութաբերություն', 'format' => 'percent', 'value' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null, 'previous' => null];
            }
        }

        $metrics[] = ['key' => 'count', 'label' => $orders ? 'Պատվերներ' : 'Վաճառքներ', 'format' => 'count', 'value' => $current->count(), 'previous' => $previous->count()];
        $metrics[] = ['key' => 'perDay', 'label' => $orders ? 'Պատվերներ օրական' : 'Վաճառքներ օրական', 'format' => 'number', 'value' => $perDay($current->count(), $currentDays), 'previous' => $perDay($previous->count(), $previousDays)];
        $perMonth = fn (int $rows, int $d) => $d === 0 ? null : $this->round($rows / $d * 30.4375);
        $metrics[] = ['key' => 'perMonth', 'label' => $orders ? 'Պատվերներ ամսական' : 'Վաճառքներ ամսական', 'format' => 'number', 'value' => $perMonth($current->count(), $currentDays), 'previous' => $perMonth($previous->count(), $previousDays)];

        if ($hasAmount) {
            $average = fn (Collection $rows) => $rows->whereNotNull('amount')->isEmpty() ? null : $this->round($rows->whereNotNull('amount')->avg('amount'));
            $metrics[] = ['key' => 'averageCheck', 'label' => 'Միջին չեկ', 'format' => 'money', 'value' => $average($current), 'previous' => $average($previous)];
        }

        if ($hasQuantity) {
            // Kilograms and pieces don't add up: figures per unit, the most used first.
            $byUnit = $current->filter(fn ($r) => $r['quantity'] !== null)->groupBy(fn ($r) => (string) $r['unit'])->sortByDesc(fn ($rows) => $rows->count());
            $previousByUnit = $previous->filter(fn ($r) => $r['quantity'] !== null)->groupBy(fn ($r) => (string) $r['unit']);

            foreach ($byUnit as $unit => $rows) {
                $unit = $unit === '' ? null : (string) $unit;
                $before = $previousByUnit->get((string) $unit, collect());
                $in = $unit ? " ({$unit})" : '';
                $priced = $rows->whereNotNull('amount');
                $quantity = $priced->sum('quantity');

                $metrics[] = ['key' => 'quantity', 'unit' => $unit, 'label' => "Քանակ{$in}", 'format' => 'number', 'value' => $this->round($rows->sum('quantity')), 'previous' => $before->isEmpty() ? null : $this->round($before->sum('quantity'))];
                $metrics[] = ['key' => 'averageQuantity', 'unit' => $unit, 'label' => 'Միջինը մեկ '.($orders ? 'պատվերում' : 'վաճառքում').$in, 'format' => 'number', 'value' => $this->round($rows->avg('quantity')), 'previous' => $before->isEmpty() ? null : $this->round($before->avg('quantity'))];
                if ($hasAmount && $quantity > 0) {
                    $metrics[] = ['key' => 'unitPrice', 'unit' => $unit, 'label' => $unit ? "1 {$unit}-ի միջին գինը" : 'Մեկ միավորի միջին գինը', 'format' => 'money', 'value' => $this->round($priced->sum('amount') / $quantity), 'previous' => null];
                }
            }
        }

        return $metrics;
    }

    /**
     * Profit of the rows whose unit cost is filled: amount − cost × quantity.
     *
     * @return array{0: int|float, 1: int, 2: int|float} profit, rows counted, their revenue
     */
    private function profit(Collection $rows): array
    {
        $counted = $rows->filter(fn ($r) => $r['cost'] !== null && $r['amount'] !== null);
        $cost = $counted->sum(fn ($r) => Decimal::multiply($r['cost'], $r['quantity'] ?? 1));
        $revenue = $counted->sum('amount');

        return [$this->round($revenue - $cost), $counted->count(), $this->round($revenue)];
    }

    /**
     * The best and the weakest day or month of the period and the average
     * one, by money when the rows have it, else by how many. Counted from
     * the first row the business ever has (months before it are not "weak"),
     * and the weakest is a finished one (today or this month isn't over).
     *
     * @param  list<array{date: string, rows: int, amount: int|float}>  $series
     * @return array<string, mixed>|null
     */
    private function peaks(array $series, string $bucket, bool $byAmount, ?CarbonImmutable $firstAt): ?array
    {
        if ($firstAt === null) {
            return null;
        }

        $from = $firstAt->format($bucket === 'month' ? 'Y-m' : 'Y-m-d');
        $points = array_values(array_filter($series, fn (array $p) => $p['date'] >= $from));
        $finished = array_slice($points, 0, -1);
        $by = $byAmount ? 'amount' : 'rows';

        if (count($points) < 2 || array_sum(array_column($points, $by)) == 0) {
            return null;
        }

        $best = collect($points)->sortByDesc($by)->first();
        $worst = $finished === [] ? null : collect($finished)->sortBy($by)->first();

        return [
            'by' => $by,
            'bucket' => $bucket,
            'best' => $best,
            'worst' => $worst && $worst['date'] !== $best['date'] ? $worst : null,
            'average' => $finished === [] ? null : $this->round(array_sum(array_column($finished, $by)) / count($finished)),
            'buckets' => count($points),
        ];
    }

    /**
     * Orders of the orders section together with the orders from the site
     * (BusinessAnalytics::summary): how many and how much in all.
     *
     * @param  array<string, mixed>  $section  a summary of the orders section
     * @param  array<string, mixed>  $site  BusinessAnalytics::summary of the same period
     * @return array<string, mixed>
     */
    public static function withSiteOrders(array $section, array $site): array
    {
        $series = collect($site['series'])->keyBy('date');

        return [
            'orders' => $section['rows']['current'] + $site['totals']['orders'],
            'previousOrders' => $section['rows']['previous'] + $site['previous']['orders'],
            'revenue' => ($section['amount']['current'] ?? 0) + $site['totals']['revenue'],
            'previousRevenue' => ($section['amount']['previous'] ?? 0) + $site['previous']['revenue'],
            'series' => array_map(fn (array $p) => [
                'date' => $p['date'],
                'rows' => $p['rows'] + ($series[$p['date']]['orders'] ?? 0),
                'amount' => $p['amount'] + ($series[$p['date']]['revenue'] ?? 0),
            ], $section['series']),
        ];
    }

    /**
     * The latest rows of the tables (by when they happened), by column
     * label, without columns about the buyer.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array<string, mixed>>
     */
    public function recentRows(Collection $tables, int $limit = 30): array
    {
        $byId = $tables->keyBy('id');

        return DataTableRow::query()
            ->whereIn('data_table_id', $tables->pluck('id'))
            ->whereNotNull('occurred_at')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['data_table_id', 'occurred_at', 'data'])
            ->map(function (DataTableRow $row) use ($byId) {
                $table = $byId[$row->data_table_id];

                return [
                    'table' => $table->name,
                    'at' => substr((string) $row->getRawOriginal('occurred_at'), 0, 16),
                    ...collect($table->columnDefinitions())
                        ->reject(fn (DataColumn $c) => self::isPersonalColumn($c) || $c->role === ColumnRole::Date)
                        ->mapWithKeys(fn (DataColumn $c) => [$c->label => $row->data[$c->key] ?? null])
                        ->filter(fn ($v) => $v !== null && $v !== '')
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public static function isPersonal(string $label): bool
    {
        return (bool) preg_match(self::PERSONAL_LABELS, $label);
    }

    /** About the buyer: by its role, or by its name when it has no role. */
    public static function isPersonalColumn(DataColumn $column): bool
    {
        return $column->role ? $column->role->isPersonal() : self::isPersonal($column->label);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable} today, start, previous start, end of today */
    private function bounds(int $days): array
    {
        // occurred_at is the business's local time, so are the bounds.
        $now = CarbonImmutable::now($this->tenants->current()->timezone ?: config('app.timezone'))->startOfDay();
        $start = $now->subDays($days - 1);

        return [$now, $start, $start->subDays($days), $now->endOfDay()];
    }

    /**
     * Most frequent values of text, list and yes/no columns, columns of the
     * same role (or, without one, the same name) in different tables taken
     * as one.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array{label: string, values: list<array{value: string, rows: int, amount: int|float|null}>, distinct: int}>
     */
    private function breakdowns(Collection $tables, Collection $current): array
    {
        $columns = $this->columnsByMeaning($tables, fn (DataColumn $c) => in_array($c->type, [DataColumnType::Text, DataColumnType::Select, DataColumnType::Boolean], true)
            && ! self::isPersonalColumn($c)
            && ! in_array($c->role, [ColumnRole::Unit, ColumnRole::Note], true));

        return $columns
            ->map(function (array $column) use ($current) {
                $groups = $current
                    ->map(fn ($r) => [...$r, 'value' => isset($column['keys'][$r['table']]) ? ($r['data'][$column['keys'][$r['table']]] ?? null) : null])
                    ->filter(fn ($r) => $r['value'] !== null && $r['value'] !== '')
                    ->groupBy(fn ($r) => match ($r['value']) {
                        true => 'Այո',
                        false => 'Ոչ',
                        default => (string) $r['value'],
                    });

                return [
                    'label' => $column['label'],
                    'role' => $column['role']?->value,
                    'filled' => $groups->sum(fn ($g) => $g->count()),
                    'distinct' => $groups->count(),
                    'values' => $groups
                        ->map(fn (Collection $rows, string $value) => [
                            'value' => $value,
                            'rows' => $rows->count(),
                            'amount' => $rows->whereNotNull('amount')->isEmpty() ? null : $this->round($rows->sum('amount')),
                        ])
                        ->sortByDesc(fn (array $v) => [$v['amount'] ?? 0, $v['rows']])
                        ->take(self::TOP_VALUES)
                        ->values()
                        ->all(),
                ];
            })
            // A column where every row has its own value (an order number) says nothing.
            ->filter(fn (array $b) => $b['values'] !== [] && $b['distinct'] < max(2, $b['filled']))
            ->sortByDesc('filled')
            ->take(self::MAX_BREAKDOWNS)
            ->map(fn (array $b) => collect($b)->except('filled')->all())
            ->values()
            ->all();
    }

    /**
     * Sums of number columns, same role or name taken as one. Quantities are
     * summed per unit (12 հատ, 3.5 կգ): kilograms and pieces don't add up.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array{label: string, unit: ?string, sum: int|float, filled: int}>
     */
    private function numbers(Collection $tables, Collection $current): array
    {
        $units = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => $t->roleColumn(ColumnRole::Unit)?->key]);

        // Quantities are in the metrics, per unit; these are the business's own number columns.
        return $this->columnsByMeaning($tables, fn (DataColumn $c) => $c->type === DataColumnType::Number && $c->role !== ColumnRole::Quantity)
            ->flatMap(function (array $column) use ($current, $units) {
                $values = $current
                    ->map(fn ($r) => [
                        'value' => isset($column['keys'][$r['table']]) ? ($r['data'][$column['keys'][$r['table']]] ?? null) : null,
                        'unit' => $column['role'] === ColumnRole::Quantity && $units[$r['table']] ? ($r['data'][$units[$r['table']]] ?? null) : null,
                    ])
                    ->filter(fn ($v) => is_int($v['value']) || is_float($v['value']));

                return $values->groupBy(fn ($v) => (string) $v['unit'])
                    ->map(fn (Collection $group, string $unit) => [
                        'label' => $column['label'],
                        'unit' => $unit === '' ? null : $unit,
                        'sum' => $this->round($group->sum('value')),
                        'filled' => $group->count(),
                    ])
                    ->sortByDesc('filled')
                    ->values();
            })
            ->values()
            ->all();
    }

    /**
     * Columns of all tables grouped by role, or by name for columns without
     * one: label, role and, per table id, the key. The date is left out.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return Collection<string, array{label: string, role: ?ColumnRole, keys: array<int, string>}>
     */
    private function columnsByMeaning(Collection $tables, callable $filter): Collection
    {
        $columns = collect();

        foreach ($tables as $table) {
            foreach ($table->columnDefinitions() as $column) {
                if ($column->role === ColumnRole::Date || ! $filter($column)) {
                    continue;
                }

                $name = $column->role ? "role:{$column->role->value}" : 'label:'.mb_strtolower(trim($column->label));
                $entry = $columns->get($name, ['label' => $column->label, 'role' => $column->role, 'keys' => []]);
                $entry['keys'][$table->id] = $column->key;
                $columns->put($name, $entry);
            }
        }

        return $columns;
    }

    /**
     * @return list<array{date: string, rows: int, amount: int|float}>
     */
    private function series(Collection $current, CarbonImmutable $from, CarbonImmutable $to, string $bucket, bool $withProfit = false): array
    {
        $format = $bucket === 'month' ? 'Y-m' : 'Y-m-d';
        $points = [];

        for ($cursor = $from; $cursor <= $to; $cursor = $bucket === 'month' ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay()) {
            $points[$cursor->format($format)] = ['date' => $cursor->format($format), 'rows' => 0, 'amount' => 0] + ($withProfit ? ['profit' => 0] : []);
        }

        foreach ($current as $row) {
            if (isset($points[$key = $row['at']->format($format)])) {
                $points[$key]['rows']++;
                $points[$key]['amount'] += $row['amount'] ?? 0;
                if ($withProfit && ($row['cost'] ?? null) !== null && $row['amount'] !== null) {
                    $points[$key]['profit'] += $row['amount'] - Decimal::multiply($row['cost'], $row['quantity'] ?? 1);
                }
            }
        }

        return array_values(array_map(fn ($p) => [...$p, 'amount' => $this->round($p['amount'])] + ($withProfit ? ['profit' => $this->round($p['profit'])] : []), $points));
    }

    /**
     * Rows and money of each of the last twelve months (this one included)
     * with the best, weakest and average month, from the business's first.
     *
     * @param  list<int>  $ids
     * @return array{series: list<array{date: string, rows: int, amount: int|float}>, peaks: ?array<string, mixed>}
     */
    private function months(array $ids, CarbonImmutable $now, bool $hasAmount, ?CarbonImmutable $firstAt): array
    {
        $from = $now->startOfMonth()->subMonthsNoOverflow(11);
        $rows = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<=', $now->endOfDay()->format('Y-m-d H:i:s'))
            ->get(['occurred_at', 'amount'])
            ->map(fn (DataTableRow $r) => ['at' => CarbonImmutable::parse($r->getRawOriginal('occurred_at')), 'amount' => $r->amount]);
        $series = $this->series($rows, $from, $now, 'month');
        $peaks = $this->peaks($series, 'month', $hasAmount, $firstAt);

        return [
            'series' => $peaks ? array_slice($series, -$peaks['buckets']) : [],
            'peaks' => $peaks,
        ];
    }

    /**
     * Rows and money by day of the week (1 Monday … 7 Sunday): which days are busy.
     *
     * @return list<array{day: int, rows: int, amount: int|float}>
     */
    private function weekdays(Collection $current): array
    {
        return collect(range(1, 7))->map(function (int $day) use ($current) {
            $rows = $current->filter(fn ($r) => $r['at']->isoWeekday() === $day);

            return ['day' => $day, 'rows' => $rows->count(), 'amount' => $this->round($rows->sum('amount'))];
        })->all();
    }

    /**
     * Rows by hour of the day, for tables whose date has a time: when
     * orders come in.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array{hour: int, rows: int}>
     */
    private function hours(Collection $tables, Collection $current): array
    {
        $timed = $tables->filter(fn (DataTable $t) => $t->dateColumn()?->type === DataColumnType::DateTime)->pluck('id')->all();
        $rows = $current->filter(fn ($r) => in_array($r['table'], $timed, true));

        if ($rows->isEmpty()) {
            return [];
        }

        return collect(range(0, 23))->map(fn (int $hour) => ['hour' => $hour, 'rows' => $rows->filter(fn ($r) => $r['at']->hour === $hour)->count()])->all();
    }

    /**
     * What the section's tables have, for the widgets (DashboardWidget::requires).
     *
     * @param  Collection<int, DataTable>  $tables
     * @return array<string, bool>
     */
    public static function has(Collection $tables): array
    {
        $any = fn (callable $test) => $tables->contains($test);
        $role = fn (ColumnRole $role) => $any(fn (DataTable $t) => $t->roleColumn($role) !== null);
        $own = fn (array $types) => $any(fn (DataTable $t) => collect($t->columnDefinitions())->contains(fn (DataColumn $c) => $c->role === null && in_array($c->type, $types, true)));

        return [
            'date' => $any(fn (DataTable $t) => $t->dateColumn() !== null),
            'datetime' => $any(fn (DataTable $t) => $t->dateColumn()?->type === DataColumnType::DateTime),
            'money' => $any(fn (DataTable $t) => $t->amountColumn() !== null),
            'cost' => $role(ColumnRole::Cost),
            'quantity' => $role(ColumnRole::Quantity),
            'item' => $role(ColumnRole::Item),
            'payment' => $role(ColumnRole::Payment),
            'channel' => $role(ColumnRole::Channel),
            'status' => $role(ColumnRole::Status),
            'ownColumns' => $own([DataColumnType::Text, DataColumnType::Select, DataColumnType::Boolean]),
            'ownNumbers' => $own([DataColumnType::Number]),
            'manyTables' => $tables->count() > 1,
        ];
    }

    private function round(int|float|null $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $rounded = round($value, 2);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}
