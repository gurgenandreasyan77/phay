<?php

namespace App\DataTables\Reshape;

use App\DataTables\DataColumn;
use App\DataTables\DataTableSchema;
use App\DataTables\Decimal;
use App\DataTables\MonthTables;
use App\DataTables\Period;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\DataColumnType;
use App\Enums\TablePeriod;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\TableReshape;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits tables into periods or puts them together into a bigger one: a
 * year's table into its months, twelve monthly tables into a year, weeks
 * into a quarter, or several tables into one of any dates (custom). Both
 * are the same thing: the rows of the chosen tables are sorted by the
 * period their date (occurred_at) falls in, and every period that has
 * rows gets a table. Four months of data make four monthly tables, not
 * twelve.
 *
 * The new tables get the columns of the chosen ones (with their roles):
 * columns of the same meaning or name become one, the others are added. A
 * row without a date goes to a table of its own, or to the newest table
 * if the owner says so. Nothing is deleted: the chosen tables are archived
 * (they stay in the tables list, out of the analytics) and the reshape can
 * be undone while the new tables are untouched.
 */
final class TableReshaper
{
    /** Tables one reshape takes at most. */
    public const MAX_TABLES = 200;

    /** Rows one reshape moves at most. */
    public const MAX_ROWS = 100000;

    /** Tables one reshape makes at most (the days of a year fit). */
    public const MAX_RESULTS = 400;

    /** Planned reshapes no one applied are forgotten after this many days. */
    public const PLANNED_DAYS = 7;

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * What a reshape would make, without making it.
     *
     * @param  Collection<int, DataTable>  $sources
     * @return array<string, mixed>
     */
    public function preview(Collection $sources, TablePeriod $kind, string $undated = TableReshape::UNDATED_SEPARATE): array
    {
        return $this->present($this->plan($sources, $kind, $undated));
    }

    /**
     * Keeps a reshape to apply later (an AI agent plans, the owner applies).
     *
     * @param  Collection<int, DataTable>  $sources
     */
    public function keep(Collection $sources, TablePeriod $kind, string $undated, ?User $user): TableReshape
    {
        $plan = $this->plan($sources, $kind, $undated);

        TableReshape::query()
            ->where('status', TableReshape::PLANNED)
            ->where('created_at', '<', now()->subDays(self::PLANNED_DAYS))
            ->delete();

        return TableReshape::create([
            'user_id' => $user?->id,
            'section' => $plan['section'],
            'period' => $kind,
            'undated' => $undated,
            'source_ids' => $plan['sources']->modelKeys(),
            'status' => TableReshape::PLANNED,
        ]);
    }

    /**
     * Makes the new tables, moves the rows into them and archives the chosen ones.
     *
     * @param  Collection<int, DataTable>  $sources
     */
    public function run(Collection $sources, TablePeriod $kind, string $undated, ?User $user): TableReshape
    {
        if ($sources->isEmpty()) {
            throw new ReshapeRefused('Ընտրեք գոնե մեկ աղյուսակ։');
        }

        return DB::transaction(function () use ($sources, $kind, $undated, $user) {
            $this->lock();
            $reshape = TableReshape::create([
                'user_id' => $user?->id,
                'section' => $sources->first()?->section,
                'period' => $kind,
                'undated' => $undated,
                'source_ids' => $sources->modelKeys(),
                'status' => TableReshape::PLANNED,
            ]);

            return $this->execute($reshape);
        });
    }

    /** Applies a kept reshape, on the tables as they are now. */
    public function apply(TableReshape $reshape): TableReshape
    {
        return DB::transaction(function () use ($reshape) {
            $this->lock();
            $reshape = TableReshape::query()->lockForUpdate()->findOrFail($reshape->id);
            if ($reshape->status !== TableReshape::PLANNED) {
                throw new ReshapeRefused($reshape->status === TableReshape::APPLIED ? 'Այս փոփոխությունն արդեն կիրառված է։' : 'Այս փոփոխությունը հետ է բերվել։ Կազմեք նորը։');
            }

            return $this->execute($reshape);
        });
    }

    /**
     * Puts the chosen tables back and removes the new ones, while no one
     * changed the new ones (a row added, edited or deleted, a column or the
     * name changed): their changes would be lost.
     */
    public function undo(TableReshape $reshape): TableReshape
    {
        return DB::transaction(function () use ($reshape) {
            $this->lock();
            $reshape = TableReshape::query()->lockForUpdate()->findOrFail($reshape->id);
            if ($reshape->status !== TableReshape::APPLIED) {
                throw new ReshapeRefused('Հետ բերել կարելի է միայն կիրառված փոփոխությունը։');
            }
            if (($why = $this->undoBlocked($reshape)) !== null) {
                throw new ReshapeRefused($why);
            }

            $results = $reshape->result_ids ?? [];
            DataTableRow::query()->whereIn('data_table_id', $results)->delete();
            DataTable::withArchived()->whereIn('id', $results)->get()->each->delete();
            DataTable::withArchived()
                ->whereIn('id', $reshape->source_ids)
                ->where('reshape_id', $reshape->id)
                ->update(['archived_at' => null, 'replaced_by_id' => null, 'reshape_id' => null]);

            $reshape->update(['status' => TableReshape::UNDONE, 'undone_at' => now()]);

            return $reshape;
        });
    }

    /** Why an applied reshape can't be undone any more; null when it can. */
    public function undoBlocked(TableReshape $reshape): ?string
    {
        if ($reshape->status !== TableReshape::APPLIED || $reshape->applied_at === null) {
            return 'Հետ բերել կարելի է միայն կիրառված փոփոխությունը։';
        }

        $counts = $reshape->summary['rows'] ?? [];
        $results = $reshape->results()->withCount('rows')->get()->keyBy('id');
        if ($results->count() !== count($reshape->result_ids ?? [])) {
            return 'Նոր աղյուսակներից մեկը ջնջվել է։';
        }
        if ($results->contains(fn (DataTable $t) => $t->archived_at !== null)) {
            return 'Նոր աղյուսակներն արդեն բաժանվել կամ միավորվել են։ Նախ հետ բերեք այդ փոփոխությունը։';
        }

        $at = $reshape->applied_at;
        $changed = $results->contains(fn (DataTable $t) => (int) $t->rows_count !== (int) ($counts[$t->id] ?? -1) || $t->updated_at > $at)
            || DataTableRow::query()->whereIn('data_table_id', $results->keys())->where('updated_at', '>', $at)->exists();
        if ($changed) {
            return 'Նոր աղյուսակներում արդեն փոփոխություններ կան (տողեր, սյունակներ կամ անուն), և հետ բերելիս դրանք կկորչեին։';
        }

        $archived = DataTable::withArchived()->whereIn('id', $reshape->source_ids)->where('reshape_id', $reshape->id)->count();

        return $archived === count($reshape->source_ids) ? null : 'Սկզբնական աղյուսակներից մեկը ջնջվել է։';
    }

    /**
     * The reshape for the admin and the AI: what it does (planned: on the
     * tables as they are now), or what it made.
     *
     * @return array<string, mixed>
     */
    public function describe(TableReshape $reshape): array
    {
        $base = [
            'id' => $reshape->id,
            'status' => $reshape->status,
            'appliedAt' => $reshape->applied_at?->toIso8601String(),
            'undoneAt' => $reshape->undone_at?->toIso8601String(),
        ];

        if ($reshape->status === TableReshape::PLANNED) {
            try {
                $sources = DataTable::query()->whereIn('id', $reshape->source_ids)->get();
                if ($sources->count() !== count($reshape->source_ids)) {
                    throw new ReshapeRefused('Աղյուսակներից մեկն արդեն ջնջվել կամ արխիվացվել է։');
                }

                return [...$base, ...$this->preview($sources, $reshape->period, $reshape->undated), 'stale' => null];
            } catch (ReshapeRefused $e) {
                return [...$base, 'section' => $reshape->section->value, 'period' => $reshape->period->value, 'periodLabel' => $reshape->period->label(), 'stale' => $e->getMessage()];
            }
        }

        $tables = fn (array $ids) => DataTable::withArchived()->whereIn('id', $ids)->withCount('rows')->orderBy('period_start')->orderBy('id')->get()
            ->map(fn (DataTable $t) => ['id' => $t->id, 'name' => $t->name, 'rows' => (int) $t->rows_count, 'archived' => $t->archived_at !== null])
            ->values()
            ->all();

        return [
            ...$base,
            'section' => $reshape->section->value,
            'period' => $reshape->period->value,
            'periodLabel' => $reshape->period->label(),
            'sources' => $tables($reshape->source_ids),
            'results' => $reshape->status === TableReshape::APPLIED ? $tables($reshape->result_ids ?? []) : [],
            'undoBlocked' => $reshape->status === TableReshape::APPLIED ? $this->undoBlocked($reshape) : null,
        ];
    }

    private function execute(TableReshape $reshape): TableReshape
    {
        $sources = DataTable::query()->whereIn('id', $reshape->source_ids)->get();
        if ($sources->count() !== count($reshape->source_ids)) {
            throw new ReshapeRefused('Աղյուսակներից մեկն արդեն ջնջվել կամ արխիվացվել է։ Կազմեք փոփոխությունը նորից։');
        }

        $plan = $this->plan($sources, $reshape->period, $reshape->undated);
        $now = now();
        $sortOrder = (int) $plan['sources']->min('sort_order');
        $description = $plan['sources']->count() === 1 ? $plan['sources']->first()->description : null;

        $made = [];
        foreach (array_values($plan['targets']) as $i => $target) {
            $made[$target['key']] = DataTable::create([
                'section' => $plan['section'],
                'name' => $target['name'],
                'description' => $description,
                'columns' => $plan['columns'],
                'period' => $target['period'] ? $target['period']->kind : TablePeriod::Custom,
                'period_start' => $target['period']?->start,
                'sort_order' => $sortOrder + $i,
            ]);
        }

        $metrics = array_map(fn (DataTable $t) => RowMetrics::for($t), $made);
        $counts = array_fill_keys(array_keys($made), 0);

        DataTableRow::query()
            ->whereIn('data_table_id', $plan['sources']->modelKeys())
            ->chunkById(1000, function (Collection $rows) use ($plan, $made, $metrics, &$counts, $now) {
                $insert = [];
                foreach ($rows as $row) {
                    $key = $this->bucket($plan, $row->getRawOriginal('occurred_at'));
                    $data = [];
                    foreach ($plan['maps'][$row->data_table_id] as $from => $to) {
                        if (array_key_exists($from, $row->data ?? [])) {
                            $data[$to] = $row->data[$from];
                        }
                    }
                    $insert[] = [
                        'tenant_id' => $made[$key]->tenant_id,
                        'data_table_id' => $made[$key]->id,
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                        'source' => $row->source,
                        'created_by_id' => $row->created_by_id,
                        'external_id' => $row->external_id,
                        'external_hash' => $row->external_hash,
                        ...$metrics[$key]($data),
                        // A row keeps when it was made; updated_at tells later edits apart.
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                    $counts[$key]++;
                }
                foreach (array_chunk($insert, 300) as $chunk) {
                    DataTableRow::query()->insert($chunk);
                }
            });

        $results = array_values(array_map(fn (DataTable $t) => $t->id, $made));
        DataTable::query()->whereIn('id', $plan['sources']->modelKeys())->update([
            'archived_at' => $now,
            'replaced_by_id' => $results[0] ?? null,
            'reshape_id' => $reshape->id,
        ]);

        $rows = [];
        foreach ($made as $key => $table) {
            $rows[$table->id] = $counts[$key];
        }
        // After every row: whatever changes the new tables later is newer.
        $reshape->update([
            'section' => $plan['section'],
            'status' => TableReshape::APPLIED,
            'source_ids' => $plan['sources']->modelKeys(),
            'result_ids' => $results,
            'summary' => ['rows' => $rows],
            'applied_at' => now(),
        ]);

        return $reshape;
    }

    /** One reshape at a time per business: two quick clicks don't move the rows twice. */
    private function lock(): void
    {
        Tenant::query()->whereKey($this->tenants->id())->lockForUpdate()->first();
    }

    /**
     * Where every row goes, and the columns of the new tables.
     *
     * @param  Collection<int, DataTable>  $sources
     * @return array<string, mixed>
     */
    private function plan(Collection $sources, TablePeriod $kind, string $undated): array
    {
        if (! in_array($undated, TableReshape::UNDATED, true)) {
            throw new ReshapeRefused('Անհայտ տարբերակ առանց ամսաթվի տողերի համար։');
        }
        if ($sources->isEmpty()) {
            throw new ReshapeRefused('Ընտրեք գոնե մեկ աղյուսակ։');
        }
        if ($sources->count() > self::MAX_TABLES) {
            throw new ReshapeRefused('Միանգամից կարելի է ընտրել առավելագույնը '.self::MAX_TABLES.' աղյուսակ։');
        }
        if ($sources->pluck('section')->unique()->count() > 1) {
            throw new ReshapeRefused('Կարելի է միավորել միայն նույն բաժնի աղյուսակները (պատվերներ, վաճառքներ կամ ծախսեր)։');
        }
        if ($sources->contains(fn (DataTable $t) => $t->archived_at !== null)) {
            throw new ReshapeRefused('Հին (արխիվացված) տարբերակը չի կարելի բաժանել կամ միավորել։');
        }
        if (($google = $sources->first(fn (DataTable $t) => $t->source === DataTable::SOURCE_GOOGLE)) !== null) {
            throw new ReshapeRefused("«{$google->name}» աղյուսակը թարմացվում է Google օրացույցից, այն չի կարելի բաժանել կամ միավորել։");
        }

        $sources = PeriodTables::infer($sources->values())
            ->sortBy([
                fn (DataTable $a, DataTable $b) => ($a->period()?->start->toDateString() ?? '') <=> ($b->period()?->start->toDateString() ?? ''),
                fn (DataTable $a, DataTable $b) => $a->sort_order <=> $b->sort_order,
                fn (DataTable $a, DataTable $b) => $a->id <=> $b->id,
            ])
            ->values();

        if ($kind->isCalendar()) {
            $undatedTables = $sources->filter(fn (DataTable $t) => $t->dateColumn() === null);
            if ($undatedTables->isNotEmpty()) {
                throw new ReshapeRefused('Ամսաթվի սյունակ չկա '.$undatedTables->map(fn (DataTable $t) => "«{$t->name}»")->join(', ').' աղյուսակում։ Նշեք այն սյունակների կարգավորումներում կամ միավորեք մեկ ազատ ժամանակահատվածի աղյուսակում։');
            }
        }

        $total = DataTableRow::query()->whereIn('data_table_id', $sources->modelKeys())->count();
        if ($total > self::MAX_ROWS) {
            throw new ReshapeRefused('Ընտրված աղյուսակներում '.number_format($total, 0, '.', ' ').' տող կա․ միանգամից կարելի է տեղափոխել առավելագույնը '.number_format(self::MAX_ROWS, 0, '.', ' ').'։');
        }

        // Rows per period: how many, their money and dates.
        $buckets = [];
        $undatedRows = 0;
        $undatedAmount = 0;
        DataTableRow::query()
            ->whereIn('data_table_id', $sources->modelKeys())
            ->select(['id', 'occurred_at', 'amount'])
            ->chunkById(5000, function (Collection $rows) use (&$buckets, &$undatedRows, &$undatedAmount, $kind) {
                foreach ($rows as $row) {
                    $at = $row->getRawOriginal('occurred_at');
                    if ($at === null && $kind->isCalendar()) {
                        $undatedRows++;
                        $undatedAmount += (float) $row->amount;

                        continue;
                    }
                    $key = $kind->isCalendar() ? Period::of($kind, (string) $at)->key() : 'all';
                    $buckets[$key] ??= ['period' => $kind->isCalendar() ? Period::of($kind, (string) $at) : null, 'rows' => 0, 'amount' => 0, 'first' => null, 'last' => null];
                    $buckets[$key]['rows']++;
                    $buckets[$key]['amount'] += (float) $row->amount;
                    if ($at !== null) {
                        $day = substr((string) $at, 0, 16);
                        $buckets[$key]['first'] = $buckets[$key]['first'] === null ? $day : min($buckets[$key]['first'], $day);
                        $buckets[$key]['last'] = $buckets[$key]['last'] === null ? $day : max($buckets[$key]['last'], $day);
                    }
                }
            });

        if (! $kind->isCalendar() && $buckets === []) {
            // Tables with no rows at all still become one table.
            $buckets['all'] = ['period' => null, 'rows' => 0, 'amount' => 0, 'first' => null, 'last' => null];
        }
        ksort($buckets);

        // Rows without a date: a table of their own, or the newest one.
        $undatedTo = null;
        if ($undatedRows > 0) {
            if ($undated === TableReshape::UNDATED_LATEST && $buckets !== []) {
                $undatedTo = array_key_last($buckets);
            } else {
                $undatedTo = 'undated';
                $buckets['undated'] = ['period' => null, 'rows' => 0, 'amount' => 0, 'first' => null, 'last' => null, 'undated' => true];
            }
            $buckets[$undatedTo]['rows'] += $undatedRows;
            $buckets[$undatedTo]['amount'] += $undatedAmount;
        }

        if ($buckets === []) {
            throw new ReshapeRefused('Ընտրված աղյուսակներում տողեր չկան՝ բաժանելու բան չկա։');
        }
        if (count($buckets) > self::MAX_RESULTS) {
            throw new ReshapeRefused('Կստացվեր '.count($buckets).' աղյուսակ, առավելագույնը '.self::MAX_RESULTS.' է։ Ընտրեք ավելի մեծ ժամանակահատված։');
        }

        // Already kept like this: one table of this very period with all its rows in it.
        $only = $sources->count() === 1 ? $sources->first() : null;
        if ($only && count($buckets) === 1) {
            $target = reset($buckets);
            $same = $kind->isCalendar()
                ? $only->period !== null && $only->period()?->kind === $kind && $only->period()->key() === $target['period']?->key()
                : $only->period() === null;
            if ($same) {
                throw new ReshapeRefused("«{$only->name}» աղյուսակն արդեն այդպես է պահվում։ Ընտրեք այլ ժամանակահատված կամ ավելացրեք այլ աղյուսակներ։");
            }
        }

        ['columns' => $columns, 'maps' => $maps, 'added' => $added] = $this->columns($sources);
        $base = $this->base($sources, $kind);

        $targets = [];
        foreach ($buckets as $key => $bucket) {
            $label = match (true) {
                $key === 'undated' => 'առանց ամսաթվի',
                $bucket['period'] !== null => $bucket['period']->label(),
                default => $this->range($bucket['first'], $bucket['last']),
            };
            $name = $base !== '' ? "{$base} · {$label}" : ($key === 'undated' ? 'Առանց ամսաթվի' : ($bucket['period'] ? $label : "Միավորված · {$label}"));
            $targets[$key] = [...$bucket, 'key' => (string) $key, 'name' => mb_substr($name, 0, 100), 'undated' => $key === 'undated'];
        }

        // Other tables already kept for one of these periods: the owner may want them in too.
        $keys = collect($targets)->pluck('period')->filter()->map(fn (Period $p) => $p->key())->all();
        $conflicts = $kind->isCalendar()
            ? PeriodTables::infer(DataTable::query()->where('section', $sources->first()->section)->whereNotIn('id', $sources->modelKeys())->get())
                ->filter(fn (DataTable $t) => $t->period()?->kind === $kind && in_array($t->period()->key(), $keys, true))
                ->values()
            : collect();

        return [
            'section' => $sources->first()->section,
            'kind' => $kind,
            'undated' => $undated,
            'sources' => $sources,
            'targets' => $targets,
            'undatedTo' => $undatedTo,
            'undatedRows' => $undatedRows,
            'rows' => $total,
            'columns' => $columns,
            'maps' => $maps,
            'added' => $added,
            'conflicts' => $conflicts,
        ];
    }

    /** The key of the new table a row of this date goes to. */
    private function bucket(array $plan, ?string $at): string
    {
        if ($at === null && $plan['kind']->isCalendar()) {
            return (string) $plan['undatedTo'];
        }

        return $plan['kind']->isCalendar() ? Period::of($plan['kind'], $at)->key() : 'all';
    }

    /**
     * The columns of the new tables: the first table's, then those of the
     * others that aren't there yet. A column is the same when it has the
     * same key, meaning (role) or name, and values of the same kind.
     *
     * @param  Collection<int, DataTable>  $sources
     * @return array{columns: list<array<string, mixed>>, maps: array<int, array<string, string>>, added: list<string>}
     */
    private function columns(Collection $sources): array
    {
        /** @var list<DataColumn> $union */
        $union = [];
        $in = [];
        $maps = [];
        $added = [];
        $normal = fn (string $label) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label)));
        $fits = fn (DataColumnType $a, DataColumnType $b) => $a === $b
            || in_array([$a, $b], [[DataColumnType::Date, DataColumnType::DateTime], [DataColumnType::DateTime, DataColumnType::Date], [DataColumnType::Number, DataColumnType::Money], [DataColumnType::Money, DataColumnType::Number]], true);

        foreach ($sources as $i => $table) {
            $maps[$table->id] = [];
            $taken = [];
            foreach ($table->columnDefinitions() as $column) {
                $match = null;
                foreach ([
                    fn (DataColumn $u) => $u->key === $column->key,
                    fn (DataColumn $u) => $column->role !== null && $u->role === $column->role,
                    fn (DataColumn $u) => $normal($u->label) === $normal($column->label),
                ] as $same) {
                    foreach ($union as $index => $u) {
                        if (! isset($taken[$index]) && $fits($u->type, $column->type) && $same($u)) {
                            $match = $index;
                            break 2;
                        }
                    }
                }

                if ($match !== null) {
                    $u = $union[$match];
                    $type = $u->type === DataColumnType::Date && $column->type === DataColumnType::DateTime ? DataColumnType::DateTime : $u->type;
                    $union[$match] = new DataColumn(
                        $u->key,
                        $u->label,
                        $type,
                        $u->required && $column->required,
                        array_values(array_unique([...$u->options, ...$column->options])),
                        $u->role ?? $column->role,
                    );
                    $in[$match][] = $table->id;
                    $taken[$match] = true;
                    $maps[$table->id][$column->key] = $u->key;

                    continue;
                }

                $keys = array_map(fn (DataColumn $u) => $u->key, $union);
                $roles = array_filter(array_map(fn (DataColumn $u) => $u->role?->value, $union));
                $union[] = new DataColumn(
                    in_array($column->key, $keys, true) ? DataTableSchema::newKey() : $column->key,
                    $column->label,
                    $column->type,
                    $column->required,
                    $column->options,
                    $column->role !== null && ! in_array($column->role->value, $roles, true) ? $column->role : null,
                );
                $index = array_key_last($union);
                $in[$index] = [$table->id];
                $taken[$index] = true;
                $maps[$table->id][$column->key] = $union[$index]->key;
                if ($i > 0) {
                    $added[] = $column->label;
                }
            }
        }

        // A column some tables don't have can't be required: their rows have no value.
        $count = $sources->count();
        $columns = [];
        foreach ($union as $index => $u) {
            $columns[] = (new DataColumn($u->key, $u->label, $u->type, $u->required && count($in[$index]) === $count, $u->options, $u->role))->toArray();
        }

        return ['columns' => $columns, 'maps' => $maps, 'added' => array_values(array_unique($added))];
    }

    /**
     * What the chosen tables are called without their period: "Վաճառքներ"
     * of "Վաճառքներ · Հունվար 2026"; nothing for tables named only by it
     * ("Հոկտեմբեր 2026", "october").
     *
     * @param  Collection<int, DataTable>  $sources
     */
    private function base(Collection $sources, TablePeriod $kind): string
    {
        return (string) $sources
            ->map(function (DataTable $table) use ($kind) {
                $name = $table->name;
                if (($period = $table->period()) !== null) {
                    $name = str_replace($period->label(), ' ', $name);
                }
                if (($named = MonthTables::fromName($name)) !== null) {
                    $name = preg_replace('/(?<!\p{L})'.preg_quote($named['word'], '/').'(?!\p{L})/iu', ' ', $name, 1);
                }
                if ($kind->isCalendar()) {
                    // The new tables' labels carry the year.
                    $name = preg_replace('/(?<!\d)20\d{2}(?!\d)/', ' ', $name);
                }
                $name = preg_replace('/\s+/u', ' ', $name);

                return preg_replace('/^[\s·\-–—,\/|:()]+|[\s·\-–—,\/|:()]+$/u', '', $name);
            })
            ->filter(fn (string $name) => $name !== '')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function range(?string $first, ?string $last): string
    {
        if ($first === null) {
            return 'բոլոր ամսաթվերը';
        }
        $day = fn (string $at) => CarbonImmutable::parse(substr($at, 0, 10))->format('d.m.Y');

        return $day($first) === $day($last) ? $day($first) : $day($first).'–'.$day($last);
    }

    /** @return array<string, mixed> */
    private function present(array $plan): array
    {
        return [
            'section' => $plan['section']->value,
            'period' => $plan['kind']->value,
            'periodLabel' => $plan['kind']->label(),
            'undated' => $plan['undated'],
            'sources' => $plan['sources']->map(fn (DataTable $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'period' => $t->period()?->label(),
            ])->values()->all(),
            'targets' => array_values(array_map(fn (array $t) => [
                'key' => $t['key'],
                'name' => $t['name'],
                'period' => $t['period']?->toArray(),
                'rows' => $t['rows'],
                'amount' => Decimal::round($t['amount']),
                'firstAt' => $t['first'],
                'lastAt' => $t['last'],
                'undated' => $t['undated'],
            ], $plan['targets'])),
            'rows' => $plan['rows'],
            'undatedRows' => $plan['undatedRows'],
            'columns' => array_column($plan['columns'], 'label'),
            'addedColumns' => $plan['added'],
            'conflicts' => $plan['conflicts']->map(fn (DataTable $t) => ['id' => $t->id, 'name' => $t->name, 'period' => $t->period()?->label()])->all(),
        ];
    }
}
