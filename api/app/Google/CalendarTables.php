<?php

namespace App\Google;

use App\DataTables\DataColumn;
use App\DataTables\DataTableSchema;
use App\DataTables\Duplicates\RowDuplicates;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\GoogleEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The business's synced Google events as an order table. The first time it
 * makes the table «Google օրացույց»; after that, when something changed in
 * the calendars, it makes a NEW version of the table and keeps the old one
 * (archived: out of the calendar and analytics, still in the tables list),
 * so nothing is ever overwritten:
 *
 * - a new event becomes a new row;
 * - a changed event updates its row, unless the row was edited by hand
 *   (its synced fields differ from what was written then): hand edits win;
 * - a deleted event drops its row, unless edited by hand;
 * - rows added by hand, and columns added to the table, are carried over.
 *
 * Nothing changed: no new table.
 *
 * When the table was split into periods (a month's table each, see
 * TableReshaper), its rows keep their events there: a changed or deleted
 * event updates or drops its row in that table, in place (unless edited by
 * hand), and a new event goes to the table of its date's period (made like
 * the others when there is none). No Google table is made again then.
 */
class CalendarTables
{
    /** Events turned into one table, at most. */
    public const MAX_EVENTS = 5000;

    public function __construct(private readonly TenantContext $tenants) {}

    /** The current table made from the calendars, if any. */
    public function current(): ?DataTable
    {
        return DataTable::query()
            ->where('section', DataTableSection::Orders)
            ->where('source', DataTable::SOURCE_GOOGLE)
            ->latest('id')
            ->first();
    }

    /**
     * What making the table would do now.
     *
     * @return array{table: ?DataTable, events: int, new: int, changed: int, removed: int, keptEdited: int, upToDate: bool}
     */
    public function preview(): array
    {
        $table = $this->current();
        $events = $this->events();
        $spread = $this->spread($table, $events);
        $plan = $this->plan($table, $this->columns($table), $events->reject(fn (GoogleEvent $e) => isset($spread['tracked'][$e->event_id])));

        return ['table' => $table, 'spread' => $spread['tables'], ...$this->counts($plan['counts'], $spread)];
    }

    /**
     * The counts of the Google table's plan and of the rows kept in period
     * tables, together. Without a Google table but with period tables, new
     * events go to the period tables (not counted twice).
     */
    private function counts(array $plan, array $spread): array
    {
        $counts = $plan;
        foreach (['changed', 'removed', 'keptEdited'] as $key) {
            $counts[$key] += $spread['counts'][$key];
        }
        $counts['upToDate'] = $counts['new'] === 0 && $counts['changed'] === 0 && $counts['removed'] === 0;

        return $counts;
    }

    /**
     * Makes the table, or its new version when something changed.
     *
     * @return array{table: DataTable, created: bool, previous: ?DataTable, events: int, new: int, changed: int, removed: int, keptEdited: int, upToDate: bool}
     */
    public function make(User $user): array
    {
        $result = RowDuplicates::paused(fn () => DB::transaction(function () use ($user) {
            // One at a time per business: two quick clicks make one version.
            Tenant::query()->whereKey($this->tenants->id())->lockForUpdate()->first();

            $previous = $this->current();
            $columns = $this->columns($previous);
            $events = $this->events();
            $spread = $this->spread($previous, $events);
            $plan = $this->plan($previous, $columns, $events->reject(fn (GoogleEvent $e) => isset($spread['tracked'][$e->event_id])));
            $counts = $this->counts($plan['counts'], $spread);

            if ($counts['upToDate'] && ($previous !== null || $spread['anchor'] !== null)) {
                return ['table' => $previous ?? $spread['anchor'], 'created' => false, 'previous' => null, 'spread' => $spread['tables'], ...$counts];
            }

            $this->applySpread($spread, $user);

            // Split into periods and no Google table: new events go to the period tables.
            if ($previous === null && $spread['anchor'] !== null) {
                $this->addToPeriods($spread['anchor'], $plan['rows'], $user);

                return ['table' => $spread['anchor'], 'created' => false, 'previous' => null, 'spread' => $spread['tables'], ...$counts];
            }

            if ($previous !== null && $plan['counts']['upToDate']) {
                return ['table' => $previous, 'created' => false, 'previous' => null, 'spread' => $spread['tables'], ...$counts];
            }

            $table = DataTable::create([
                'section' => DataTableSection::Orders,
                'name' => $this->name(),
                'description' => 'Google օրացույցի իրադարձություններից։',
                // The same columns as the previous version (with the business's own), or the default ones.
                'columns' => $columns,
                'period' => TablePeriod::Custom,
                'source' => DataTable::SOURCE_GOOGLE,
                'sort_order' => $previous?->sort_order
                    ?? (DataTable::query()->where('section', DataTableSection::Orders)->max('sort_order') ?? 0) + 1,
            ]);

            $this->insertRows($table, $plan['rows'], $user);

            $previous?->forceFill(['archived_at' => now(), 'replaced_by_id' => $table->id])->save();

            return ['table' => $table, 'created' => true, 'previous' => $previous, 'spread' => $spread['tables'], ...$counts];
        }));

        app(RowDuplicates::class)->refresh();

        return $result;
    }

    /**
     * The rows made from events in the business's other current order
     * tables (a Google table split into months): what syncing them changes,
     * in place.
     *
     * @param  Collection<int, GoogleEvent>  $events
     * @return array{tracked: array<string, true>, tables: list<array{id: int, name: string}>, anchor: ?DataTable, updates: list<array>, deletes: list<int>, counts: array{changed: int, removed: int, keptEdited: int}}
     */
    private function spread(?DataTable $google, Collection $events): array
    {
        $tables = DataTable::query()
            ->where('section', DataTableSection::Orders)
            ->when($google, fn ($q) => $q->whereKeyNot($google->id))
            ->get()
            ->keyBy('id');
        $rows = $tables->isEmpty() ? collect() : DataTableRow::query()
            ->whereIn('data_table_id', $tables->keys()->all())
            ->whereNotNull('external_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $byEvent = $events->keyBy('event_id');
        $result = ['tracked' => [], 'tables' => [], 'anchor' => null, 'updates' => [], 'deletes' => [], 'counts' => ['changed' => 0, 'removed' => 0, 'keptEdited' => 0]];
        $columns = [];

        foreach ($rows as $row) {
            $table = $tables[$row->data_table_id];
            $columns[$table->id] ??= $this->syncedColumns($table);
            $result['tracked'][$row->external_id] = true;
            $result['tables'][$table->id] = ['id' => $table->id, 'name' => $table->name];
            // New events go next to the latest one.
            $result['anchor'] = $table;

            $data = $row->data ?? [];
            $edited = self::hash($columns[$table->id], $data) !== $row->external_hash;
            $event = $byEvent->get($row->external_id);

            if ($event === null) {
                if ($edited) {
                    $result['counts']['keptEdited']++;
                } else {
                    $result['counts']['removed']++;
                    $result['deletes'][] = $row->id;
                }

                continue;
            }

            $fresh = $this->values($columns[$table->id], $event);
            $freshHash = self::hash($columns[$table->id], $fresh);
            if ($freshHash === $row->external_hash) {
                continue;
            }
            if ($edited) {
                $result['counts']['keptEdited']++;

                continue;
            }
            $result['counts']['changed']++;
            $result['updates'][] = ['row' => $row, 'data' => [...$data, ...$fresh], 'hash' => $freshHash];
        }

        $result['tables'] = array_values($result['tables']);

        return $result;
    }

    private function applySpread(array $spread, User $user): void
    {
        foreach ($spread['updates'] as $update) {
            $update['row']->forceFill(['data' => $update['data'], 'source' => DataTableRow::SOURCE_GOOGLE, 'external_hash' => $update['hash']])->save();
        }
        if ($spread['deletes'] !== []) {
            DataTableRow::query()->whereIn('id', $spread['deletes'])->delete();
        }
    }

    /**
     * New events into the tables of their dates' periods, like the calendar
     * adds an order (PeriodTables::forDate), made like the anchor table.
     *
     * @param  list<array{data: array, source: string, external_id: ?string, external_hash: ?string, created_by_id: ?int}>  $rows  the plan's rows (only new events: there is no Google table)
     */
    private function addToPeriods(DataTable $anchor, array $rows, User $user): void
    {
        $anchorColumns = $this->syncedColumns($anchor);
        $events = GoogleEvent::query()->whereIn('event_id', array_filter(array_column($rows, 'external_id')))->get()->keyBy('event_id');

        foreach ($rows as $planned) {
            $event = $events->get($planned['external_id']);
            if ($event === null) {
                continue;
            }
            $values = $this->values($anchorColumns, $event);
            $date = $anchor->dateColumn() ? ($values[$anchor->dateColumn()->key] ?? null) : null;
            [$table] = is_string($date) ? PeriodTables::forDate($anchor, $date) : [$anchor];
            $columns = $this->syncedColumns($table);
            $fresh = $this->values($columns, $event);

            $table->rows()->make([
                'data' => $fresh,
                'source' => DataTableRow::SOURCE_GOOGLE,
                'external_id' => $event->event_id,
                'external_hash' => self::hash($columns, $fresh),
                'created_by_id' => $user->id,
            ])->setRelation('table', $table)->save();
        }
    }

    /**
     * The events of the business's chosen calendars, one per Google event
     * id (an event shared by two calendars counts once), by start.
     *
     * @return Collection<int, GoogleEvent>
     */
    private function events(): Collection
    {
        return GoogleEvent::query()
            ->whereHas('calendar', fn ($q) => $q->where('selected', true))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(self::MAX_EVENTS)
            ->get()
            ->unique('event_id')
            ->values();
    }

    /**
     * The rows of the new version and what changed.
     *
     * @param  list<array>  $definitions  the columns of the new version
     * @param  Collection<int, GoogleEvent>  $events
     * @return array{rows: list<array{data: array, source: string, external_id: ?string, external_hash: ?string, created_by_id: ?int}>, counts: array{events: int, new: int, changed: int, removed: int, keptEdited: int, upToDate: bool}}
     */
    private function plan(?DataTable $table, array $definitions, Collection $events): array
    {
        $columns = $this->syncedColumns(new DataTable(['columns' => $definitions]));

        $counts = ['events' => $events->count(), 'new' => 0, 'changed' => 0, 'removed' => 0, 'keptEdited' => 0];
        $rows = [];
        $byEvent = $events->keyBy('event_id');
        $done = [];

        foreach ($table?->rows()->orderBy('occurred_at')->orderBy('id')->get() ?? [] as $row) {
            $data = $row->data ?? [];

            if ($row->external_id === null) {
                // Added by hand: always carried over.
                $rows[] = $this->rowFrom($data, $row->source, null, null, $row->created_by_id);

                continue;
            }

            $edited = self::hash($columns, $data) !== $row->external_hash;
            $event = $byEvent->get($row->external_id);
            $done[$row->external_id] = true;

            if ($event === null) {
                if ($edited) {
                    $counts['keptEdited']++;
                    $rows[] = $this->rowFrom($data, $row->source, $row->external_id, $row->external_hash, $row->created_by_id);
                } else {
                    $counts['removed']++;
                }

                continue;
            }

            $fresh = $this->values($columns, $event);
            $freshHash = self::hash($columns, $fresh);

            if ($freshHash === $row->external_hash || $edited) {
                if ($edited && $freshHash !== $row->external_hash) {
                    $counts['keptEdited']++;
                }
                $rows[] = $this->rowFrom($data, $row->source, $row->external_id, $row->external_hash, $row->created_by_id);

                continue;
            }

            $counts['changed']++;
            $rows[] = $this->rowFrom([...$data, ...$fresh], DataTableRow::SOURCE_GOOGLE, $row->external_id, $freshHash, $row->created_by_id);
        }

        foreach ($events as $event) {
            if (isset($done[$event->event_id])) {
                continue;
            }

            $fresh = $this->values($columns, $event);
            $counts['new']++;
            $rows[] = $this->rowFrom($fresh, DataTableRow::SOURCE_GOOGLE, $event->event_id, self::hash($columns, $fresh), null);
        }

        $counts['upToDate'] = $counts['new'] === 0 && $counts['changed'] === 0 && $counts['removed'] === 0;

        return ['rows' => $rows, 'counts' => $counts];
    }

    /** The columns of the next version: the current one's (with the business's own), or the default ones. */
    private function columns(?DataTable $table): array
    {
        return $table?->columns ?? DataTableSchema::columns($this->defaultColumns());
    }

    private function rowFrom(array $data, string $source, ?string $externalId, ?string $hash, ?int $createdBy): array
    {
        return ['data' => $data, 'source' => $source, 'external_id' => $externalId, 'external_hash' => $hash, 'created_by_id' => $createdBy];
    }

    /**
     * The columns an event fills, by role: those of the table that still
     * have the role (a column whose role was removed is the business's own now).
     *
     * @return array<string, DataColumn> by role value
     */
    private function syncedColumns(DataTable $table): array
    {
        $columns = [];
        foreach (EventFields::ROLES as $role) {
            if (($column = $table->roleColumn($role)) !== null) {
                $columns[$role->value] = $column;
            }
        }

        return $columns;
    }

    /**
     * An event's values as stored in the table's columns.
     *
     * @param  array<string, DataColumn>  $columns
     * @return array<string, mixed> by column key
     */
    private function values(array $columns, GoogleEvent $event): array
    {
        $raw = EventFields::of($event);
        $values = [];

        foreach ($columns as $role => $column) {
            $value = $raw[$role] ?? null;
            if ($column->type === DataColumnType::Date && is_string($value)) {
                $value = substr($value, 0, 10);
            }
            // A value the column can't take (a select without that option) is left empty.
            [$parsed, $error] = $column->parse($value);
            $values[$column->key] = $error === null ? $parsed : null;
        }

        return $values;
    }

    /**
     * The synced fields of a row, as one hash: another hash later means the
     * row was edited by hand, or the event changed.
     *
     * @param  array<string, DataColumn>  $columns
     */
    private static function hash(array $columns, array $data): string
    {
        $values = [];
        foreach ($columns as $role => $column) {
            $values[$role] = $data[$column->key] ?? null;
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @param list<array{data: array, source: string, external_id: ?string, external_hash: ?string, created_by_id: ?int}> $rows */
    private function insertRows(DataTable $table, array $rows, User $user): void
    {
        $metrics = RowMetrics::for($table);
        $now = now();

        collect($rows)
            ->map(fn (array $row) => [
                'tenant_id' => $table->tenant_id,
                'data_table_id' => $table->id,
                'data' => json_encode($row['data'], JSON_UNESCAPED_UNICODE),
                'source' => $row['source'],
                'external_id' => $row['external_id'],
                'external_hash' => $row['external_hash'],
                'created_by_id' => $row['created_by_id'] ?? $user->id,
                ...$metrics($row['data']),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(300)
            ->each(fn (Collection $chunk) => DataTableRow::query()->insert($chunk->values()->all()));
    }

    /** «Google օրացույց · 21.10.2026 14:30», in the business's time. */
    private function name(): string
    {
        $timezone = $this->tenants->current()->timezone ?: config('app.timezone');

        return 'Google օրացույց · '.now($timezone)->format('d.m.Y H:i');
    }

    /** @return list<array{label: string, type: string, role: string}> */
    private function defaultColumns(): array
    {
        return array_map(fn (ColumnRole $role) => [
            'label' => $role->labels(DataTableSection::Orders)['hy'],
            'type' => $role->types()[0]->value,
            'role' => $role->value,
            'options' => [],
        ], EventFields::ROLES);
    }
}
