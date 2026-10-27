<?php

namespace App\DataTables\Changes;

use App\DataTables\DataColumn;
use App\DataTables\DataTableSchema;
use App\DataTables\Period;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Http\Controllers\Api\V1\Admin\DataTableController;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\TableChange;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Changes of the business's tables an AI agent prepares and the owner
 * applies from a card in the chat: a new table (with rows, if given), a
 * table's name, period and columns (added, renamed, retyped options,
 * meanings, removed), rows added, edited or deleted, tables deleted.
 *
 * Preparing checks everything the way the admin's forms do (column types,
 * meanings, dates, numbers) and keeps exactly what will change: the table
 * before and after, every row's old and new values. Nothing is written
 * until apply(), which refuses when the table or the rows changed since
 * (the card would show something else than what happens). An applied
 * change is undone while nobody touched what it changed; deleting tables
 * can't be undone.
 */
class TableChanges
{
    /** Rows one change adds or edits at most. */
    public const MAX_ROWS = 100;

    /** Rows one change deletes at most. */
    public const MAX_DELETE = 200;

    /** Tables one change deletes at most. */
    public const MAX_TABLES = 20;

    /** Rows the card shows; the rest are counted. */
    public const SHOWN_ROWS = 30;

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * A new table: section, name, its columns ({label, type, role?, options?})
     * and, if given, its first rows.
     *
     * @param  array<string, mixed>  $input
     */
    public function planCreate(array $input, ?User $user): TableChange
    {
        $section = DataTableSection::tryFrom((string) ($input['section'] ?? ''))
            ?? throw new ChangeRefused('Ընտրեք բաժինը՝ orders, sales կամ expenses։');
        $this->ensureSection($section);

        $name = trim((string) ($input['name'] ?? ''));
        $columns = array_map(fn ($c) => $this->columnInput((array) $c), array_values((array) ($input['columns'] ?? [])));
        $this->validate(['name' => $name, 'columns' => $columns], [
            'name' => ['required', 'string', 'max:100'],
            ...DataTableSchema::rules(),
        ]);
        if (DataTable::query()->where('section', $section)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new ChangeRefused("«{$name}» անունով աղյուսակ այս բաժնում արդեն կա։ Ընտրեք այլ անուն։");
        }

        $columns = $this->schema(fn () => DataTableSchema::columns($columns));
        [$period, $periodStart] = $this->period($input);

        // The rows are read by the new columns, as the table will read them.
        $draft = new DataTable(['section' => $section, 'name' => $name, 'columns' => $columns]);
        $rows = $this->parseRows($draft, (array) ($input['rows'] ?? []));

        return $this->keep(TableChange::CREATE_TABLE, $section, null, [
            'table' => [
                'name' => $name,
                'description' => filled($input['description'] ?? null) ? mb_substr(trim((string) $input['description']), 0, 1000) : null,
                'period' => $period?->value,
                'period_start' => $periodStart,
                'columns' => $columns,
            ],
            'rows' => $rows,
        ], $user);
    }

    /**
     * A table's name, description, period and columns: columns added,
     * renamed, given a meaning (or none), other options, removed.
     *
     * @param  array<string, mixed>  $input
     */
    public function planEdit(DataTable $table, array $input, ?User $user): TableChange
    {
        $this->ensureEditable($table);
        $columns = array_map(fn (DataColumn $c) => $c->toArray(), $table->columnDefinitions());

        foreach ((array) ($input['rename_columns'] ?? []) as $rename) {
            $i = $this->columnIndex($columns, (string) ($rename['column'] ?? ''));
            $columns[$i]['label'] = trim((string) ($rename['label'] ?? ''));
        }
        foreach ((array) ($input['set_roles'] ?? []) as $set) {
            $i = $this->columnIndex($columns, (string) ($set['column'] ?? ''));
            $role = $set['role'] ?? null;
            if ($role !== null && $role !== '' && ColumnRole::tryFrom((string) $role) === null) {
                throw new ChangeRefused("Չկա «{$role}» իմաստ։");
            }
            $columns[$i]['role'] = $role === '' ? null : $role;
        }
        foreach ((array) ($input['set_options'] ?? []) as $set) {
            $i = $this->columnIndex($columns, (string) ($set['column'] ?? ''));
            if ($columns[$i]['type'] !== DataColumnType::Select->value) {
                throw new ChangeRefused("«{$columns[$i]['label']}» սյունակը ընտրությամբ չէ։");
            }
            $columns[$i]['options'] = array_values(array_map('strval', (array) ($set['options'] ?? [])));
        }
        $removed = [];
        foreach ((array) ($input['remove_columns'] ?? []) as $ref) {
            $i = $this->columnIndex($columns, (string) $ref);
            $removed[] = $columns[$i]['key'];
            unset($columns[$i]);
        }
        $columns = array_values($columns);
        foreach ((array) ($input['add_columns'] ?? []) as $column) {
            $columns[] = $this->columnInput((array) $column);
        }

        $name = array_key_exists('name', $input) ? trim((string) $input['name']) : $table->name;
        $description = array_key_exists('description', $input)
            ? (filled($input['description']) ? mb_substr(trim((string) $input['description']), 0, 1000) : null)
            : $table->description;
        $this->validate(['name' => $name, 'columns' => $columns], ['name' => ['required', 'string', 'max:100'], ...DataTableSchema::rules()]);
        $after = $this->schema(fn () => DataTableSchema::columns($columns, $table));

        [$period, $periodStart] = array_key_exists('period', $input)
            ? $this->period($input)
            : [$table->period, $table->period_start?->toDateString()];

        $before = $this->snapshot($table);
        $next = [
            'name' => $name,
            'description' => $description,
            'period' => $period?->value,
            'period_start' => $periodStart,
            'columns' => $after,
        ];
        if ($next === $before) {
            throw new ChangeRefused('Աղյուսակում ոչինչ չի փոխվում։');
        }

        return $this->keep(TableChange::EDIT_TABLE, $table->section, $table, [
            'before' => $before,
            'after' => $next,
            'removed' => $removed,
            'rows' => $table->rows()->count(),
        ], $user);
    }

    /**
     * Tables deleted with their rows: no undo.
     *
     * @param  Collection<int, DataTable>  $tables
     */
    public function planDelete(Collection $tables, ?User $user): TableChange
    {
        if ($tables->isEmpty() || $tables->count() > self::MAX_TABLES) {
            throw new ChangeRefused('Ընտրեք 1-ից '.self::MAX_TABLES.' աղյուսակ։');
        }
        if ($tables->pluck('section')->unique()->count() > 1) {
            throw new ChangeRefused('Մեկ անգամից ջնջեք մեկ բաժնի աղյուսակներ։');
        }
        $tables->each(fn (DataTable $t) => $this->ensureSection($t->section));

        $counts = DataTableRow::query()->whereIn('data_table_id', $tables->modelKeys())
            ->groupBy('data_table_id')->selectRaw('data_table_id, COUNT(*) as total')->pluck('total', 'data_table_id');

        return $this->keep(TableChange::DELETE_TABLES, $tables->first()->section, null, [
            'tables' => $tables->map(fn (DataTable $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'rows' => (int) ($counts[$t->id] ?? 0),
                'updated_at' => $t->updated_at?->toIso8601String(),
            ])->values()->all(),
        ], $user);
    }

    /**
     * Rows to add: each an object of values by column label or key. A row
     * without a date gets today's, as in the admin.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function planAdd(DataTable $table, array $rows, ?User $user): TableChange
    {
        $this->ensureEditable($table);
        $parsed = $this->parseRows($table, $rows);
        if ($parsed === []) {
            throw new ChangeRefused('Տողեր չկան։');
        }

        return $this->keep(TableChange::ADD_ROWS, $table->section, $table, [
            'columns' => $table->columns,
            'rows' => $parsed,
        ], $user);
    }

    /**
     * Rows to edit: {id, values} with only the values that change.
     *
     * @param  list<array<string, mixed>>  $changes
     */
    public function planUpdate(DataTable $table, array $changes, ?User $user): TableChange
    {
        $this->ensureEditable($table);
        if ($changes === [] || count($changes) > self::MAX_ROWS) {
            throw new ChangeRefused('Ընտրեք 1-ից '.self::MAX_ROWS.' տող։');
        }

        $existing = $table->rows()->whereIn('id', array_map(fn ($c) => (int) ($c['id'] ?? 0), $changes))->get()->keyBy('id');
        $rows = [];
        $errors = [];
        foreach (array_values($changes) as $n => $change) {
            $row = $existing->get((int) ($change['id'] ?? 0));
            if (! $row) {
                $errors[] = 'Տող #'.(int) ($change['id'] ?? 0).'՝ այս աղյուսակում չկա։';

                continue;
            }
            $values = $this->byKey($table->columnDefinitions(), (array) ($change['values'] ?? []), $errors, '#'.$row->id);
            [$data, $rowErrors] = DataTableSchema::parseRow($table, [...($row->data ?? []), ...$values]);
            foreach ($rowErrors as $key => $error) {
                $errors[] = "Տող #{$row->id}, «{$this->label($table, $key)}»՝ {$error}";
            }
            if ($rowErrors === [] && $data != ($row->data ?? [])) {
                $rows[] = ['id' => $row->id, 'before' => $row->data ?? [], 'after' => $data];
            }
            unset($n);
        }
        $this->refuseErrors($errors);
        if ($rows === []) {
            throw new ChangeRefused('Այս տողերում ոչինչ չի փոխվում։');
        }

        return $this->keep(TableChange::UPDATE_ROWS, $table->section, $table, ['rows' => $rows], $user);
    }

    /** @param  list<int>  $ids */
    public function planDeleteRows(DataTable $table, array $ids, ?User $user): TableChange
    {
        $this->ensureEditable($table);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || count($ids) > self::MAX_DELETE) {
            throw new ChangeRefused('Ընտրեք 1-ից '.self::MAX_DELETE.' տող։');
        }

        $rows = $table->rows()->whereIn('id', $ids)->orderBy('id')->get();
        $missing = array_diff($ids, $rows->modelKeys());
        if ($missing !== []) {
            throw new ChangeRefused('Այս տողերը աղյուսակում չկան՝ #'.implode(', #', $missing).'։');
        }

        return $this->keep(TableChange::DELETE_ROWS, $table->section, $table, [
            'rows' => $rows->map(fn (DataTableRow $r) => ['id' => $r->id, 'data' => $r->data ?? []])->all(),
        ], $user);
    }

    /** Does a planned change, on the tables as they are now (refused when they changed since). */
    public function apply(TableChange $change): TableChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if ($change->status !== TableChange::PLANNED) {
                throw new ChangeRefused(match ($change->status) {
                    TableChange::APPLIED => 'Այս փոփոխությունն արդեն կիրառված է։',
                    TableChange::DISCARDED => 'Այս փոփոխությունը չեղարկված է։',
                    default => 'Այս փոփոխությունը հետ է բերվել։ Խնդրեք նորը։',
                });
            }
            if (($why = $this->stale($change)) !== null) {
                throw new ChangeRefused($why);
            }

            $result = match ($change->kind) {
                TableChange::CREATE_TABLE => $this->doCreate($change),
                TableChange::EDIT_TABLE => $this->doEdit($change),
                TableChange::DELETE_TABLES => $this->doDeleteTables($change),
                TableChange::ADD_ROWS => $this->doAdd($change),
                TableChange::UPDATE_ROWS => $this->doUpdate($change, 'after'),
                TableChange::DELETE_ROWS => $this->doDeleteRows($change),
            };

            $change->update(['status' => TableChange::APPLIED, 'applied_at' => now(), 'result' => $result]);

            return $change;
        });
    }

    /** Puts things back as they were before the change, while nobody touched what it changed. */
    public function undo(TableChange $change): TableChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if (($why = $this->undoBlocked($change)) !== null) {
                throw new ChangeRefused($why);
            }

            $table = $change->table();
            $result = $change->result ?? [];
            switch ($change->kind) {
                case TableChange::CREATE_TABLE:
                    DataTable::withArchived()->whereKey($result['table'])->first()?->delete();
                    break;
                case TableChange::EDIT_TABLE:
                    $table->fill($this->fields($change->payload['before']))->save();
                    RowMetrics::recompute($table);
                    break;
                case TableChange::ADD_ROWS:
                    $table->rows()->whereIn('id', $result['rows'] ?? [])->delete();
                    break;
                case TableChange::UPDATE_ROWS:
                    $this->doUpdate($change, 'before');
                    break;
                case TableChange::DELETE_ROWS:
                    $result['restored'] = $this->insertRows($table, array_column($change->payload['rows'], 'data'), $change->user_id);
                    break;
            }

            $change->update(['status' => TableChange::UNDONE, 'undone_at' => now(), 'result' => $result]);

            return $change;
        });
    }

    /** The owner decided not to do it. */
    public function discard(TableChange $change): TableChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if ($change->status !== TableChange::PLANNED) {
                throw new ChangeRefused('Չեղարկել կարելի է միայն դեռ չկիրառված փոփոխությունը։');
            }
            $change->update(['status' => TableChange::DISCARDED]);

            return $change;
        });
    }

    /** Why a planned change can't be applied as shown any more; null when it can. */
    public function stale(TableChange $change): ?string
    {
        if ($change->kind === TableChange::CREATE_TABLE) {
            $name = $change->payload['table']['name'];

            return DataTable::query()->where('section', $change->section)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()
                ? "«{$name}» անունով աղյուսակ արդեն կա։"
                : null;
        }

        if ($change->kind === TableChange::DELETE_TABLES) {
            $planned = collect($change->payload['tables']);
            $tables = DataTable::withArchived()->whereIn('id', $planned->pluck('id'))->withCount('rows')->get()->keyBy('id');
            foreach ($planned as $t) {
                $now = $tables->get($t['id']);
                if (! $now) {
                    return "«{$t['name']}» աղյուսակն արդեն ջնջված է։";
                }
                if ((int) $now->rows_count !== (int) $t['rows']) {
                    return "«{$t['name']}» աղյուսակում տողերը փոխվել են։ Խնդրեք նորից։";
                }
            }

            return null;
        }

        $table = $change->table();
        if (! $table) {
            return 'Աղյուսակն արդեն ջնջված է։';
        }
        if ($table->archived_at !== null) {
            return 'Աղյուսակն արդեն փոխարինվել է նոր տարբերակով (բաժանվել կամ միավորվել է)։';
        }

        return match ($change->kind) {
            TableChange::EDIT_TABLE => $this->snapshot($table) === $change->payload['before'] ? null : 'Աղյուսակի անունը կամ սյունակներն արդեն փոխվել են։ Խնդրեք նորից։',
            TableChange::ADD_ROWS => $table->columns == $change->payload['columns'] ? null : 'Աղյուսակի սյունակներն արդեն փոխվել են։ Խնդրեք նորից։',
            TableChange::UPDATE_ROWS => $this->rowsDiffer($table, $change->payload['rows'], 'before') ? 'Այս տողերն արդեն փոխվել կամ ջնջվել են։ Խնդրեք նորից։' : null,
            TableChange::DELETE_ROWS => $this->rowsDiffer($table, $change->payload['rows'], 'data') ? 'Այս տողերն արդեն փոխվել կամ ջնջվել են։ Խնդրեք նորից։' : null,
            default => null,
        };
    }

    /** Why an applied change can't be undone; null when it can. */
    public function undoBlocked(TableChange $change): ?string
    {
        if ($change->status !== TableChange::APPLIED) {
            return 'Հետ բերել կարելի է միայն կիրառված փոփոխությունը։';
        }
        if ($change->kind === TableChange::DELETE_TABLES) {
            return 'Ջնջված աղյուսակները հետ բերել հնարավոր չէ։';
        }

        $result = $change->result ?? [];
        if ($change->kind === TableChange::CREATE_TABLE) {
            $table = DataTable::withArchived()->withCount('rows')->find($result['table'] ?? 0);
            if (! $table) {
                return 'Աղյուսակն արդեն ջնջված է։';
            }

            return (int) $table->rows_count !== count($change->payload['rows'] ?? []) || $table->updated_at > $change->applied_at
                || $table->rows()->where('updated_at', '>', $change->applied_at)->exists()
                ? 'Նոր աղյուսակում արդեն փոփոխություններ կան, և հետ բերելիս դրանք կկորչեին։'
                : null;
        }

        $table = $change->table();
        if (! $table || $table->archived_at !== null) {
            return 'Աղյուսակն արդեն ջնջված կամ փոխարինված է։';
        }

        return match ($change->kind) {
            TableChange::EDIT_TABLE => $this->snapshot($table) === $change->payload['after'] ? null : 'Աղյուսակն այդ պահից նորից է փոխվել։',
            TableChange::ADD_ROWS => $table->rows()->whereIn('id', $result['rows'] ?? [])->where('updated_at', '>', $change->applied_at)->exists()
                || $table->rows()->whereIn('id', $result['rows'] ?? [])->count() !== count($result['rows'] ?? [])
                ? 'Ավելացված տողերն արդեն փոխվել կամ ջնջվել են։'
                : null,
            TableChange::UPDATE_ROWS => $this->rowsDiffer($table, $change->payload['rows'], 'after') ? 'Այս տողերն այդ պահից նորից են փոխվել։' : null,
            default => null,
        };
    }

    /**
     * The change for the card and the AI: what it does, value by value.
     *
     * @return array<string, mixed>
     */
    public function describe(TableChange $change): array
    {
        $payload = $change->payload;
        $table = $change->kind === TableChange::CREATE_TABLE
            ? (isset($change->result['table']) ? DataTable::withArchived()->find($change->result['table']) : null)
            : $change->table();
        $columns = match ($change->kind) {
            TableChange::CREATE_TABLE => $payload['table']['columns'],
            TableChange::EDIT_TABLE => $payload['after']['columns'],
            TableChange::DELETE_TABLES => [],
            TableChange::ADD_ROWS => $payload['columns'],
            default => $table?->columns ?? [],
        };

        $rows = match ($change->kind) {
            TableChange::CREATE_TABLE, TableChange::ADD_ROWS => array_map(fn ($data) => ['values' => $data], $payload['rows'] ?? []),
            TableChange::UPDATE_ROWS => array_map(fn ($r) => [
                'id' => $r['id'],
                'values' => $r['after'],
                'before' => $r['before'],
                'changed' => array_values(array_keys(array_filter($r['after'], fn ($v, $k) => ($r['before'][$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH))),
            ], $payload['rows']),
            TableChange::DELETE_ROWS => array_map(fn ($r) => ['id' => $r['id'], 'values' => $r['data']], $payload['rows']),
            default => [],
        };

        return [
            'id' => $change->id,
            'kind' => $change->kind,
            'status' => $change->status,
            'section' => $change->section->value,
            'title' => $this->title($change),
            'table' => $table ? ['id' => $table->id, 'name' => $table->name, 'archived' => $table->archived_at !== null] : null,
            'tables' => $change->kind === TableChange::DELETE_TABLES ? $payload['tables'] : null,
            'changes' => $this->lines($change),
            'columns' => array_map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'type' => $c['type']], $columns),
            'rows' => array_slice($rows, 0, self::SHOWN_ROWS),
            'rowsTotal' => count($rows),
            'stale' => $change->status === TableChange::PLANNED ? $this->stale($change) : null,
            'undoable' => $change->kind !== TableChange::DELETE_TABLES,
            'undoBlocked' => $change->status === TableChange::APPLIED ? $this->undoBlocked($change) : null,
            'appliedAt' => $change->applied_at?->toIso8601String(),
            'undoneAt' => $change->undone_at?->toIso8601String(),
        ];
    }

    /** One line saying what the change does, in Armenian. */
    public function title(TableChange $change): string
    {
        $payload = $change->payload;
        $name = $change->kind === TableChange::CREATE_TABLE ? $payload['table']['name'] : ($change->table()?->name ?? $payload['before']['name'] ?? '');
        $count = fn (array $list) => count($list);

        return match ($change->kind) {
            TableChange::CREATE_TABLE => "Նոր աղյուսակ՝ «{$name}»".(($payload['rows'] ?? []) !== [] ? ', '.$count($payload['rows']).' տողով' : ''),
            TableChange::EDIT_TABLE => "«{$name}» աղյուսակի փոփոխություն",
            TableChange::DELETE_TABLES => 'Ջնջել '.$count($payload['tables']).' աղյուսակ',
            TableChange::ADD_ROWS => "Ավելացնել {$count($payload['rows'])} տող «{$name}»-ում",
            TableChange::UPDATE_ROWS => "Փոխել {$count($payload['rows'])} տող «{$name}»-ում",
            TableChange::DELETE_ROWS => "Ջնջել {$count($payload['rows'])} տող «{$name}»-ից",
            default => $change->kind,
        };
    }

    /**
     * What changes in the table itself, a line each (Armenian).
     *
     * @return list<string>
     */
    private function lines(TableChange $change): array
    {
        $payload = $change->payload;
        $section = $change->section;

        if ($change->kind === TableChange::CREATE_TABLE) {
            $t = $payload['table'];

            return array_values(array_filter([
                'Բաժին՝ '.self::sectionLabel($section),
                $t['period'] ? 'Ժամանակահատված՝ '.$this->periodLabel($t['period'], $t['period_start']) : null,
                ...array_map(fn ($c) => 'Սյունակ «'.$c['label'].'»՝ '.$this->columnLabel($c, $section), $t['columns']),
            ]));
        }

        if ($change->kind === TableChange::DELETE_TABLES) {
            return array_map(fn ($t) => "«{$t['name']}»՝ {$t['rows']} տող", $payload['tables']);
        }

        if ($change->kind !== TableChange::EDIT_TABLE) {
            return [];
        }

        $before = $payload['before'];
        $after = $payload['after'];
        $old = collect($before['columns'])->keyBy('key');
        $new = collect($after['columns'])->keyBy('key');
        $lines = [];

        if ($before['name'] !== $after['name']) {
            $lines[] = "Անունը՝ «{$before['name']}» → «{$after['name']}»";
        }
        if ($before['description'] !== $after['description']) {
            $lines[] = $after['description'] ? 'Նկարագրությունը՝ «'.$after['description'].'»' : 'Նկարագրությունը կհեռացվի';
        }
        if ($before['period'] !== $after['period'] || $before['period_start'] !== $after['period_start']) {
            $lines[] = 'Ժամանակահատված՝ '.($after['period'] ? $this->periodLabel($after['period'], $after['period_start']) : 'առանց');
        }
        foreach ($new as $key => $c) {
            $was = $old->get($key);
            if (! $was) {
                $lines[] = 'Նոր սյունակ «'.$c['label'].'»՝ '.$this->columnLabel($c, $section);

                continue;
            }
            if ($was['label'] !== $c['label']) {
                $lines[] = "Սյունակ «{$was['label']}» → «{$c['label']}»";
            }
            if (($was['role'] ?? null) !== ($c['role'] ?? null)) {
                $lines[] = '«'.$c['label'].'» սյունակի իմաստը՝ '.($c['role'] ? $this->roleLabel($c['role'], $section) : 'առանց իմաստի');
            }
            if (($was['options'] ?? []) !== ($c['options'] ?? []) && $c['type'] === DataColumnType::Select->value) {
                $lines[] = '«'.$c['label'].'» տարբերակները՝ '.implode(', ', $c['options']);
            }
        }
        foreach ($old as $key => $c) {
            if (! $new->has($key)) {
                $lines[] = 'Ջնջել «'.$c['label'].'» սյունակը'.($payload['rows'] > 0 ? ' (դրա արժեքները '.$payload['rows'].' տողում կկորչեն)' : '');
            }
        }

        return $lines;
    }

    public static function sectionLabel(DataTableSection $section): string
    {
        return match ($section) {
            DataTableSection::Orders => 'Պատվերներ',
            DataTableSection::Sales => 'Վաճառքներ',
            DataTableSection::Expenses => 'Ծախսեր',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match (DataColumnType::tryFrom($type)) {
            DataColumnType::Text => 'տեքստ',
            DataColumnType::Number => 'թիվ',
            DataColumnType::Money => 'գումար',
            DataColumnType::Date => 'ամսաթիվ',
            DataColumnType::DateTime => 'ամսաթիվ և ժամ',
            DataColumnType::Select => 'ընտրություն',
            DataColumnType::Boolean => 'այո/ոչ',
            default => $type,
        };
    }

    private function columnLabel(array $column, DataTableSection $section): string
    {
        return self::typeLabel($column['type'])
            .($column['role'] ? ', '.$this->roleLabel($column['role'], $section) : '')
            .($column['type'] === DataColumnType::Select->value && $column['options'] ? ' ('.implode(', ', $column['options']).')' : '');
    }

    private function roleLabel(string $role, DataTableSection $section): string
    {
        return mb_strtolower(ColumnRole::tryFrom($role)?->labels($section)['hy'] ?? $role);
    }

    private function periodLabel(string $period, ?string $start): string
    {
        $kind = TablePeriod::from($period);

        return $kind->isCalendar() && $start ? Period::of($kind, $start)->label() : $kind->label();
    }

    private function doCreate(TableChange $change): array
    {
        $t = $change->payload['table'];
        $table = DataTable::create([
            'section' => $change->section,
            ...$this->fields($t),
            'sort_order' => (DataTable::query()->where('section', $change->section)->max('sort_order') ?? 0) + 1,
        ]);
        PeriodTables::infer(collect([$table]));

        return ['table' => $table->id, 'rows' => $this->insertRows($table, $change->payload['rows'] ?? [], $change->user_id)];
    }

    private function doEdit(TableChange $change): array
    {
        $table = $change->table();
        $table->fill($this->fields($change->payload['after']))->save();
        RowMetrics::recompute($table);

        return ['table' => $table->id];
    }

    private function doDeleteTables(TableChange $change): array
    {
        $ids = array_column($change->payload['tables'], 'id');
        DataTable::withArchived()->whereIn('id', $ids)->get()->each->delete();

        return ['tables' => $ids];
    }

    private function doAdd(TableChange $change): array
    {
        return ['rows' => $this->insertRows($change->table(), $change->payload['rows'], $change->user_id)];
    }

    /** Writes the rows' "after" values (apply) or their "before" ones (undo). */
    private function doUpdate(TableChange $change, string $side): array
    {
        $table = $change->table();
        $rows = $table->rows()->whereIn('id', array_column($change->payload['rows'], 'id'))->get()->keyBy('id');
        foreach ($change->payload['rows'] as $r) {
            $rows[$r['id']]->setRelation('table', $table)->update(['data' => $r[$side]]);
        }

        return ['rows' => array_column($change->payload['rows'], 'id')];
    }

    private function doDeleteRows(TableChange $change): array
    {
        $ids = array_column($change->payload['rows'], 'id');
        $change->table()->rows()->whereIn('id', $ids)->delete();

        return ['rows' => $ids];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  parsed data
     * @return list<int> the new rows' ids
     */
    private function insertRows(DataTable $table, array $rows, ?int $userId): array
    {
        $ids = [];
        foreach ($rows as $data) {
            $row = $table->rows()->make(['data' => $data, 'source' => DataTableRow::SOURCE_MANUAL, 'created_by_id' => $userId]);
            $row->setRelation('table', $table)->save();
            $ids[] = $row->id;
        }

        return $ids;
    }

    /** The table's stored fields from a snapshot. */
    private function fields(array $snapshot): array
    {
        return [
            'name' => $snapshot['name'],
            'description' => $snapshot['description'],
            'period' => $snapshot['period'],
            'period_start' => $snapshot['period_start'],
            'columns' => $snapshot['columns'],
        ];
    }

    /** What an edit compares: the table's name, description, period and columns. */
    private function snapshot(DataTable $table): array
    {
        return [
            'name' => $table->name,
            'description' => $table->description,
            'period' => $table->period?->value,
            'period_start' => $table->period_start?->toDateString(),
            'columns' => array_map(fn (DataColumn $c) => $c->toArray(), $table->columnDefinitions()),
        ];
    }

    /** Whether any of the rows is gone or holds other values than `$side` of the plan. */
    private function rowsDiffer(DataTable $table, array $planned, string $side): bool
    {
        $now = $table->rows()->whereIn('id', array_column($planned, 'id'))->pluck('data', 'id');

        foreach ($planned as $r) {
            if (! $now->has($r['id']) || ($now[$r['id']] ?? []) != $r[$side]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $rows  values by column label or key
     * @return list<array<string, mixed>> parsed data
     */
    private function parseRows(DataTable $table, array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw new ChangeRefused('Մեկ անգամից կարելի է ավելացնել առավելագույնը '.self::MAX_ROWS.' տող։');
        }

        $columns = $table->columnDefinitions();
        $date = $table->dateColumn();
        $today = now($this->tenants->current()->timezone ?: config('app.timezone'));
        $parsed = [];
        $errors = [];

        foreach (array_values($rows) as $n => $values) {
            $values = $this->byKey($columns, (array) $values, $errors, (string) ($n + 1));
            if ($date && trim((string) ($values[$date->key] ?? '')) === '') {
                $values[$date->key] = $today->format($date->type === DataColumnType::DateTime ? 'Y-m-d H:i' : 'Y-m-d');
            }
            [$data, $rowErrors] = DataTableSchema::parseRow($table, $values);
            foreach ($rowErrors as $key => $error) {
                $errors[] = 'Տող '.($n + 1).", «{$this->label($table, $key)}»՝ {$error}";
            }
            $parsed[] = $data;
        }
        $this->refuseErrors($errors);

        return $parsed;
    }

    /**
     * Values by column key from values by label or key.
     *
     * @param  list<DataColumn>  $columns
     * @param  list<string>  $errors
     */
    private function byKey(array $columns, array $values, array &$errors, string $row): array
    {
        $out = [];
        foreach ($values as $ref => $value) {
            $column = $this->findColumn($columns, (string) $ref);
            if (! $column) {
                $errors[] = "Տող {$row}՝ «{$ref}» սյունակ չկա (կան՝ ".implode(', ', array_map(fn (DataColumn $c) => $c->label, $columns)).')։';

                continue;
            }
            $out[$column->key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }

        return $out;
    }

    /** @param  list<DataColumn>  $columns */
    private function findColumn(array $columns, string $ref): ?DataColumn
    {
        $ref = mb_strtolower(trim($ref));
        foreach ($columns as $column) {
            if ($column->key === $ref || mb_strtolower($column->label) === $ref) {
                return $column;
            }
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $columns */
    private function columnIndex(array $columns, string $ref): int
    {
        $ref = mb_strtolower(trim($ref));
        foreach ($columns as $i => $column) {
            if ($column['key'] === $ref || mb_strtolower($column['label']) === $ref) {
                return $i;
            }
        }

        throw new ChangeRefused("«{$ref}» սյունակ չկա։ Կան՝ ".implode(', ', array_column($columns, 'label')).'։');
    }

    private function label(DataTable $table, string $key): string
    {
        return collect($table->columnDefinitions())->firstWhere('key', $key)?->label ?? $key;
    }

    /** A column as the schema reads it: label, type, role (left out: guessed from the label), options. */
    private function columnInput(array $column): array
    {
        return array_filter([
            'label' => trim((string) ($column['label'] ?? '')),
            'type' => (string) ($column['type'] ?? DataColumnType::Text->value),
            'options' => isset($column['options']) ? array_values(array_map('strval', (array) $column['options'])) : null,
            'required' => isset($column['required']) ? (bool) $column['required'] : null,
        ], fn ($v) => $v !== null) + (array_key_exists('role', $column) ? ['role' => $column['role'] === '' ? null : $column['role']] : []);
    }

    /** @return array{0: ?TablePeriod, 1: ?string} the kind and its first day */
    private function period(array $input): array
    {
        if (($input['period'] ?? null) === null || $input['period'] === '') {
            return [null, null];
        }
        $kind = TablePeriod::tryFrom((string) $input['period'])
            ?? throw new ChangeRefused('period՝ '.implode(', ', TablePeriod::values()).'։');
        if (! $kind->isCalendar()) {
            return [$kind, null];
        }
        $day = isset($input['period_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['period_date'])
            ? (string) $input['period_date']
            : now($this->tenants->current()->timezone ?: config('app.timezone'))->toDateString();

        return [$kind, Period::of($kind, $day)->start->toDateString()];
    }

    private function validate(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ChangeRefused(implode(' ', $validator->errors()->all()));
        }
    }

    /** @param  callable(): list<array>  $build */
    private function schema(callable $build): array
    {
        try {
            return $build();
        } catch (ValidationException $e) {
            throw new ChangeRefused(implode(' ', collect($e->errors())->flatten()->all()));
        }
    }

    /** @param  list<string>  $errors */
    private function refuseErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new ChangeRefused(implode("\n", array_slice($errors, 0, 15)));
        }
    }

    private function ensureSection(DataTableSection $section): void
    {
        if (! DataTableController::sectionAllowed($section)) {
            throw new ChangeRefused('Այս բաժնի աղյուսակները ձեզ հասանելի չեն։');
        }
    }

    private function ensureEditable(DataTable $table): void
    {
        $this->ensureSection($table->section);
        if ($table->archived_at !== null) {
            throw new ChangeRefused("«{$table->name}» աղյուսակը հին տարբերակ է (արխիվում)։ Աշխատեք ընթացիկի հետ։");
        }
    }

    private function keep(string $kind, DataTableSection $section, ?DataTable $table, array $payload, ?User $user): TableChange
    {
        return TableChange::create([
            'user_id' => $user?->id,
            'kind' => $kind,
            'section' => $section,
            'data_table_id' => $table?->id,
            'payload' => $payload,
            'status' => TableChange::PLANNED,
        ]);
    }

    /** The change, locked, with the business's tables (one change at a time). */
    private function locked(TableChange $change): TableChange
    {
        Tenant::query()->whereKey($this->tenants->id())->lockForUpdate()->first();

        return TableChange::query()->lockForUpdate()->findOrFail($change->id);
    }
}
