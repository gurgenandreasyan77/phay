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
use Laravel\Pennant\Feature;

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
     * Tables of the sections whose module is on.
     *
     * @return Collection<int, DataTable>
     */
    public function tables(): Collection
    {
        $sections = collect(DataTableSection::cases())
            ->filter(fn (DataTableSection $s) => Feature::active($s->feature()->value))
            ->map(fn (DataTableSection $s) => $s->value);

        return DataTable::query()->whereIn('section', $sections)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * One summary per section that has tables.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(int $days): array
    {
        return $this->tables()
            ->groupBy(fn (DataTable $t) => $t->section->value)
            ->map(fn (Collection $tables, string $section) => $this->section(DataTableSection::from($section), $tables, $days))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DataTable>  $tables  tables of the section
     * @return array<string, mixed>
     */
    public function section(DataTableSection $section, Collection $tables, int $days): array
    {
        [$now, $start, $previousStart, $end] = $this->bounds($days);
        $ids = $tables->pluck('id')->all();

        $current = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<=', $end->format('Y-m-d H:i:s'))
            ->orderByDesc('occurred_at')
            ->limit(self::MAX_ROWS)
            ->get(['data_table_id', 'occurred_at', 'amount', 'data'])
            ->map(fn (DataTableRow $r) => [
                'table' => $r->data_table_id,
                'at' => CarbonImmutable::parse($r->getRawOriginal('occurred_at')),
                'amount' => $r->amount,
                'data' => $r->data ?? [],
            ]);

        $previous = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->where('occurred_at', '>=', $previousStart->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $start->format('Y-m-d H:i:s'))
            ->selectRaw('COUNT(*) as rows_count, SUM(amount) as amount_sum')
            ->first();

        $totals = DataTableRow::query()
            ->whereIn('data_table_id', $ids)
            ->groupBy('data_table_id')
            ->selectRaw('data_table_id, COUNT(*) as total, SUM(CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END) as without_date, MIN(occurred_at) as first_at, MAX(occurred_at) as last_at')
            ->get()
            ->keyBy('data_table_id');

        $hasAmount = $tables->contains(fn (DataTable $t) => $t->amountColumn() !== null);
        $currentAmount = $this->round($current->sum('amount'));
        $priced = $current->whereNotNull('amount');

        return [
            'section' => $section->value,
            'period' => ['days' => $days, 'from' => $start->toDateString(), 'to' => $now->toDateString()],
            'rows' => [
                'total' => (int) $totals->sum('total'),
                // Rows with no date are in no period; the screen says how many.
                'withoutDate' => (int) $totals->sum('without_date'),
                'current' => $current->count(),
                'previous' => (int) $previous->rows_count,
            ],
            'amount' => $hasAmount ? [
                'current' => $currentAmount,
                'previous' => $this->round((float) $previous->amount_sum),
                'average' => $priced->isEmpty() ? null : $this->round($priced->avg('amount')),
            ] : null,
            'series' => $this->series($current, $start, $now, $days > 90 ? 'month' : 'day'),
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

        return $this->columnsByMeaning($tables, fn (DataColumn $c) => $c->type === DataColumnType::Number)
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
    private function series(Collection $current, CarbonImmutable $from, CarbonImmutable $to, string $bucket): array
    {
        $format = $bucket === 'month' ? 'Y-m' : 'Y-m-d';
        $points = [];

        for ($cursor = $from; $cursor <= $to; $cursor = $bucket === 'month' ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay()) {
            $points[$cursor->format($format)] = ['date' => $cursor->format($format), 'rows' => 0, 'amount' => 0];
        }

        foreach ($current as $row) {
            if (isset($points[$key = $row['at']->format($format)])) {
                $points[$key]['rows']++;
                $points[$key]['amount'] += $row['amount'] ?? 0;
            }
        }

        return array_values(array_map(fn ($p) => [...$p, 'amount' => $this->round($p['amount'])], $points));
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
