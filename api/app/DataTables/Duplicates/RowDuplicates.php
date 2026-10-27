<?php

namespace App\DataTables\Duplicates;

use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\RowDuplicateDismissal;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * The same order, sale or expense written in two or more tables of a
 * section (a copy of a table, a month's table made from a bigger one, a
 * file imported into two tables): rows of the same day whose fields agree.
 *
 * - Exact copy: every field that is filled on either row is the same on
 *   both (the time too, when both have one). The copy is counted once: the
 *   others get duplicate_of_id and are left out of the calendar, analytics,
 *   finance and the AI (DataTableRow::scopeCounted). A row from Google is
 *   the one kept counted, else the oldest.
 * - Possible copy: no field differs, but one row lacks something the other
 *   has (an address, a time, a column the other table doesn't have). Both
 *   still count; the owner is asked (review()).
 * - A field that differs on both (another performer, another price, another
 *   time) means two different records.
 *
 * Rows of one table are never compared with each other: two identical
 * sales of a day in one table are two sales. The owner settles each group:
 * keep all (dismiss(): never asked or counted once again) or delete copies.
 * Flags follow every change: rows saved or deleted refresh their days,
 * bulk writes and table changes refresh everything.
 */
final class RowDuplicates
{
    public const EXACT = 'exact';

    public const NEAR = 'near';

    /** The sections whose rows are compared (each with its own). */
    public const SECTIONS = [DataTableSection::Orders, DataTableSection::Sales, DataTableSection::Expenses];

    /** Days read per query. */
    private const DAYS_PER_QUERY = 60;

    /** Rows of one day of a section compared at most (a busy day of several tables). */
    public const MAX_DAY_ROWS = 600;

    /** Roles not compared: the day and time are compared apart; numbers and money as the row's amount. */
    private const NOT_COMPARED = [ColumnRole::Date, ColumnRole::Time, ColumnRole::OrderNumber, ColumnRole::Amount, ColumnRole::UnitPrice];

    private static int $paused = 0;

    /** Runs a bulk write without refreshing per row: refresh() after it. */
    public static function paused(Closure $callback): mixed
    {
        self::$paused++;

        try {
            return $callback();
        } finally {
            self::$paused--;
        }
    }

    public static function isPaused(): bool
    {
        return self::$paused > 0;
    }

    /**
     * Sets which rows are copies, for the given days ("Y-m-d") or all of
     * them. Returns how many rows are left out as copies.
     *
     * @param  ?list<string>  $days
     */
    public function refresh(?array $days = null): int
    {
        if ($days === []) {
            return 0;
        }

        $tables = $this->tables();
        $copies = [];
        foreach ($this->groups($tables, $days) as $group) {
            foreach ($group['copies'] as $id => $primary) {
                $copies[$id] = $primary;
            }
        }

        // Rows flagged before that are no copies now (in those days, or anywhere).
        $flagged = DataTableRow::query()->whereNotNull('duplicate_of_id');
        if ($days !== null) {
            $flagged->where(function ($q) use ($days) {
                foreach ($days as $day) {
                    $q->orWhere(fn ($q) => $this->onDay($q, $day));
                }
            });
        }
        $stale = $flagged->pluck('duplicate_of_id', 'id')
            ->filter(fn ($primary, $id) => ($copies[$id] ?? null) !== (int) $primary)
            ->keys();
        foreach ($stale->chunk(500) as $ids) {
            DataTableRow::query()->whereIn('id', $ids->all())->update(['duplicate_of_id' => null]);
        }

        $now = DataTableRow::query()->whereIn('id', array_keys($copies) ?: [0])->pluck('duplicate_of_id', 'id');
        $byPrimary = [];
        foreach ($copies as $id => $primary) {
            if ((int) ($now[$id] ?? 0) !== $primary) {
                $byPrimary[$primary][] = $id;
            }
        }
        foreach ($byPrimary as $primary => $ids) {
            DataTableRow::query()->whereIn('id', $ids)->update(['duplicate_of_id' => $primary]);
        }

        return count($copies);
    }

    /** After a row was saved or deleted: its days (before and after). */
    public function touched(DataTableRow $row): void
    {
        if (self::isPaused()) {
            return;
        }

        $days = array_values(array_unique(array_filter([
            substr((string) $row->getRawOriginal('occurred_at'), 0, 10),
            substr((string) ($row->getAttributes()['occurred_at'] ?? ''), 0, 10),
        ])));
        $days = array_values(array_filter($days, fn (string $day) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1));

        if ($days !== []) {
            $this->refresh($days);
        }
    }

    /**
     * The groups the owner should look at: rows of different tables that
     * are (or may be) one record, newest days first.
     *
     * @return list<array{key: string, section: string, day: string, kind: string, rows: list<array>}>
     */
    public function review(?DataTableSection $section = null, ?int $limit = 100, ?array $days = null, ?Closure $allowed = null): array
    {
        $tables = $this->tables()->filter(fn (DataTable $t) => ($section === null || $t->section === $section)
            && ($allowed === null || $allowed($t->section)));

        $groups = $this->groups($tables, $days);
        usort($groups, fn ($a, $b) => [$b['day'], $a['section']] <=> [$a['day'], $b['section']]);

        $out = [];
        foreach ($groups as $group) {
            foreach ($group['clusters'] as $cluster) {
                $out[] = [
                    'key' => implode('-', array_map(fn ($e) => $e['id'], $cluster['entries'])),
                    'section' => $group['section'],
                    'day' => $group['day'],
                    'kind' => $cluster['kind'],
                    'rows' => array_map(fn (array $entry) => [
                        'id' => $entry['id'],
                        'tableId' => $entry['table']->id,
                        'table' => $entry['table']->name,
                        'time' => $entry['time'],
                        'amount' => $entry['amount'],
                        'source' => $entry['source'],
                        'values' => $entry['shown'],
                        // Left out of the counts as a copy of that row.
                        'copyOf' => $group['copies'][$entry['id']] ?? null,
                        // Fields this row lacks that another row of the group has.
                        'missing' => array_values(array_diff($cluster['labels'], array_keys(array_filter($entry['shown'], fn ($v) => $v !== null && $v !== '')))),
                    ], $cluster['entries']),
                ];
                if ($limit !== null && count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** How many groups wait for the owner. */
    public function count(?Closure $allowed = null): int
    {
        return count($this->review(null, null, null, $allowed));
    }

    /**
     * The owner said these rows are different records: never counted once,
     * never asked about again.
     *
     * @param  list<int>  $ids
     */
    public function dismiss(array $ids, ?int $userId): void
    {
        $rows = DataTableRow::query()->whereIn('id', $ids)->get(['id', 'occurred_at']);
        $ids = $rows->modelKeys();
        sort($ids);

        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                RowDuplicateDismissal::query()->firstOrCreate(['row_id' => $a, 'other_row_id' => $b], ['user_id' => $userId]);
            }
        }

        $this->refresh($rows->map(fn (DataTableRow $r) => substr((string) $r->getRawOriginal('occurred_at'), 0, 10))->filter()->unique()->values()->all());
    }

    /**
     * Whether a synced Google event is already a row of a table (the same
     * start and fields): the calendar then shows the row only.
     *
     * @param  array<string, ?string>  $fields  raw values by role (App\Google\EventFields::of)
     * @param  Collection<int, DataTableRow>  $rows  the day's rows, with their table
     */
    public function eventIsRow(array $fields, ?string $time, float|int|null $amount, Collection $rows): bool
    {
        $event = ['id' => 0, 'table' => null, 'time' => $time, 'amount' => $amount, 'fields' => []];
        foreach ($fields as $role => $value) {
            $role = ColumnRole::tryFrom($role);
            if ($role === null || in_array($role, self::NOT_COMPARED, true)) {
                continue;
            }
            $event['fields'][$role->value] = self::normal($value, $role);
        }

        foreach ($rows as $row) {
            $entry = $this->entry($row, $row->table, self::spec($row->table));
            // A timed event is a row only of the same time, not of an all-day row.
            if ($time !== null && $entry['time'] === null) {
                continue;
            }
            if ($this->compare($event, $entry) !== null) {
                return true;
            }
        }

        return false;
    }

    /** The business's current tables of the compared sections. */
    private function tables(): Collection
    {
        return DataTable::query()
            ->whereIn('section', array_map(fn (DataTableSection $s) => $s->value, self::SECTIONS))
            ->get()
            ->filter(fn (DataTable $table) => $table->dateColumn() !== null)
            ->keyBy('id');
    }

    /**
     * The days with rows of two or more tables of a section, compared.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return list<array{section: string, day: string, copies: array<int, int>, clusters: list<array>}>
     */
    private function groups(Collection $tables, ?array $days): array
    {
        if ($tables->count() < 2 || $days === []) {
            return [];
        }

        $counts = DataTableRow::query()
            ->whereIn('data_table_id', $tables->keys()->all())
            ->whereNotNull('occurred_at')
            ->when($days !== null, fn ($q) => $q->where(function ($q) use ($days) {
                foreach ($days as $day) {
                    $q->orWhere(fn ($q) => $this->onDay($q, $day));
                }
            }))
            ->selectRaw('data_table_id, date(occurred_at) as day')
            ->groupByRaw('data_table_id, date(occurred_at)')
            ->get();

        // section => day => tables with rows that day
        $spread = [];
        foreach ($counts as $count) {
            $table = $tables[$count->data_table_id];
            $day = substr((string) $count->day, 0, 10);
            $spread[$table->section->value][$day][] = $table->id;
        }

        $dismissed = null;
        $specs = $tables->map(fn (DataTable $t) => self::spec($t));
        $groups = [];

        foreach ($spread as $section => $byDay) {
            $candidates = array_keys(array_filter($byDay, fn (array $ids) => count($ids) > 1));
            foreach (array_chunk($candidates, self::DAYS_PER_QUERY) as $chunk) {
                $ids = array_values(array_unique(array_merge(...array_map(fn ($d) => $byDay[$d], $chunk))));
                $rows = DataTableRow::query()
                    ->whereIn('data_table_id', $ids)
                    ->where(function ($q) use ($chunk) {
                        foreach ($chunk as $day) {
                            $q->orWhere(fn ($q) => $this->onDay($q, $day));
                        }
                    })
                    ->orderBy('id')
                    ->get(['id', 'data_table_id', 'data', 'occurred_at', 'amount', 'source', 'external_id'])
                    ->groupBy(fn (DataTableRow $row) => substr((string) $row->getRawOriginal('occurred_at'), 0, 10));

                $dismissed ??= $this->dismissed();
                foreach ($rows as $day => $dayRows) {
                    $entries = $dayRows->take(self::MAX_DAY_ROWS)
                        ->map(fn (DataTableRow $row) => $this->entry($row, $tables[$row->data_table_id], $specs[$row->data_table_id]))
                        ->all();
                    $group = $this->compareDay(array_values($entries), $dismissed);
                    if ($group['clusters'] !== []) {
                        $groups[] = ['section' => $section, 'day' => (string) $day, ...$group];
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * @param  list<array>  $entries
     * @param  array<string, true>  $dismissed
     * @return array{copies: array<int, int>, clusters: list<array>}
     */
    private function compareDay(array $entries, array $dismissed): array
    {
        $n = count($entries);
        $all = new Links;
        $exact = new Links;
        $kinds = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $entries[$i];
                $b = $entries[$j];
                if ($a['table']->id === $b['table']->id || isset($dismissed[min($a['id'], $b['id']).'-'.max($a['id'], $b['id'])])) {
                    continue;
                }
                $kind = $this->compare($a, $b);
                if ($kind === null) {
                    continue;
                }
                $all->join($a['id'], $b['id']);
                $kinds[$a['id']][] = $kind;
                $kinds[$b['id']][] = $kind;
                if ($kind === self::EXACT) {
                    $exact->join($a['id'], $b['id']);
                }
            }
        }

        $byId = array_column($entries, null, 'id');
        $copies = [];
        foreach ($exact->groups() as $ids) {
            $members = array_map(fn ($id) => $byId[$id], $ids);
            // Kept counted: a row from Google (the calendar syncs it), else the oldest.
            usort($members, fn ($a, $b) => [$a['external'] === null, $a['id']] <=> [$b['external'] === null, $b['id']]);
            foreach (array_slice($members, 1) as $member) {
                // Two rows of one table are never one record: both stay.
                if ($member['table']->id !== $members[0]['table']->id) {
                    $copies[$member['id']] = $members[0]['id'];
                }
            }
        }

        $clusters = [];
        foreach ($all->groups() as $ids) {
            sort($ids);
            $members = array_map(fn ($id) => $byId[$id], $ids);
            $near = collect($ids)->contains(fn ($id) => in_array(self::NEAR, $kinds[$id] ?? [], true));
            $labels = array_values(array_unique(array_merge(...array_map(
                fn ($e) => array_keys(array_filter($e['shown'], fn ($v) => $v !== null && $v !== '')),
                $members,
            ))));
            $clusters[] = ['entries' => $members, 'kind' => $near ? self::NEAR : self::EXACT, 'labels' => $labels];
        }

        return ['copies' => $copies, 'clusters' => $clusters];
    }

    /**
     * Exact, possible (near) or no copy.
     */
    private function compare(array $a, array $b): ?string
    {
        $same = 0;
        $missing = 0;

        if ($a['time'] !== null && $b['time'] !== null) {
            if ($a['time'] !== $b['time']) {
                return null;
            }
        } elseif ($a['time'] !== null || $b['time'] !== null) {
            $missing++;
        }

        if ($a['amount'] !== null && $b['amount'] !== null) {
            if (abs((float) $a['amount'] - (float) $b['amount']) > 0.004) {
                return null;
            }
        } elseif ($a['amount'] !== null || $b['amount'] !== null) {
            $missing++;
        }

        foreach (array_keys($a['fields'] + $b['fields']) as $key) {
            $x = $a['fields'][$key] ?? null;
            $y = $b['fields'][$key] ?? null;
            if ($x === null && $y === null) {
                continue;
            }
            if ($x === null || $y === null) {
                $missing++;

                continue;
            }
            if ($x !== $y) {
                return null;
            }
            $same++;
        }

        // A day and an amount alone don't make two rows one record: some field too.
        if ($same === 0) {
            return null;
        }

        return $missing === 0 ? self::EXACT : self::NEAR;
    }

    /**
     * What is compared of a table's row: its columns by role (or by label
     * when they have none), and whether its rows have a time.
     *
     * @return array{columns: list<array{key: string, compare: string, role: ?ColumnRole, label: string}>, timed: bool, clocked: bool}
     */
    private static function spec(DataTable $table): array
    {
        $columns = [];
        foreach ($table->columnDefinitions() as $column) {
            $compared = ! in_array($column->role, self::NOT_COMPARED, true);
            $columns[] = [
                'key' => $column->key,
                'compare' => $compared ? ($column->role?->value ?? 'label:'.self::normal($column->label)) : null,
                'role' => $column->role,
                'label' => $column->label,
                'type' => $column->type,
            ];
        }

        return [
            'columns' => $columns,
            'timed' => $table->dateColumn()?->type === DataColumnType::DateTime,
            'clocked' => $table->roleColumn(ColumnRole::Time) !== null,
        ];
    }

    private function entry(DataTableRow $row, DataTable $table, array $spec): array
    {
        $data = $row->data ?? [];
        $at = (string) $row->getRawOriginal('occurred_at');
        $clock = strlen($at) >= 16 ? substr($at, 11, 5) : null;
        $time = $spec['timed'] || ($spec['clocked'] && $clock !== '00:00') ? $clock : null;

        $fields = [];
        $shown = [];
        foreach ($spec['columns'] as $column) {
            $value = $data[$column['key']] ?? null;
            if ($column['role'] !== ColumnRole::Date) {
                $shown[$column['label']] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            if ($column['compare'] !== null) {
                $fields[$column['compare']] = self::normal($value, $column['role']);
            }
        }

        return [
            'id' => $row->id,
            'table' => $table,
            'time' => $time,
            'amount' => $row->amount,
            'source' => $row->source,
            'external' => $row->external_id,
            'fields' => array_filter($fields, fn ($v) => $v !== null),
            'shown' => $shown,
        ];
    }

    /** A value as compared: case, spaces and number formats aside; empty is null. */
    private static function normal(mixed $value, ?ColumnRole $role = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }
        if (! is_string($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
        if ($role === ColumnRole::Phone) {
            $digits = preg_replace('/\D+/', '', $text) ?? '';
            // 091 23 45 67 and +374 91 23 45 67 are one phone.
            $text = strlen($digits) > 8 ? substr($digits, -8) : $digits;
        } elseif (is_numeric(str_replace([' ', ','], ['', '.'], $text))) {
            $text = rtrim(rtrim(number_format((float) str_replace([' ', ','], ['', '.'], $text), 4, '.', ''), '0'), '.');
        }

        return $text === '' ? null : $text;
    }

    /** @return array<string, true> "smaller-bigger" row ids the owner kept apart */
    private function dismissed(): array
    {
        return RowDuplicateDismissal::query()->get(['row_id', 'other_row_id'])
            ->mapWithKeys(fn (RowDuplicateDismissal $d) => [$d->row_id.'-'.$d->other_row_id => true])
            ->all();
    }

    private function onDay($query, string $day): void
    {
        $start = CarbonImmutable::parse($day)->startOfDay();
        $query->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $start->addDay()->format('Y-m-d H:i:s'));
    }
}
