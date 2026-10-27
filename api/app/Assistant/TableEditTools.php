<?php

namespace App\Assistant;

use App\DataTables\Changes\TableChanges;
use App\DataTables\DataColumn;
use App\DataTables\Duplicates\RowDuplicates;
use App\DataTables\TableAnalytics;
use App\Enums\BusinessRole;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Http\Controllers\Api\V1\Admin\DataTableController;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\TableChange;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The table agent's work on the business's tables: reading a table's rows
 * with their ids, and preparing changes (a new table, its columns and their
 * meanings, rows added, edited or deleted, tables deleted). Every change is
 * only planned (TableChanges) and comes back to the chat as a card listing
 * exactly what changes; the owner applies it with the card's button.
 *
 * Only for a person who designs the business's tables (owner, admin), and
 * expenses tables only for those who see them. Columns about buyers
 * (names, phones, addresses) are never read back to the model; it writes
 * them when the owner dictates them, and adds the columns a dictated
 * detail has no place for in the same card (add_rows new_columns).
 */
class TableEditTools
{
    public const ROWS = 'table_rows';

    public const CREATE = 'create_table';

    public const EDIT = 'edit_table';

    public const DELETE_TABLES = 'delete_tables';

    public const ADD = 'add_rows';

    public const UPDATE = 'update_rows';

    public const DELETE_ROWS = 'delete_rows';

    public const DUPLICATES = 'find_duplicates';

    public const NAMES = [self::ROWS, self::DUPLICATES, self::CREATE, self::EDIT, self::DELETE_TABLES, self::ADD, self::UPDATE, self::DELETE_ROWS];

    /** The tools that prepare a change (a card), not only read. */
    public const WRITES = [self::CREATE, self::EDIT, self::DELETE_TABLES, self::ADD, self::UPDATE, self::DELETE_ROWS];

    public function __construct(
        private readonly TableChanges $changes,
        private readonly ReplyActions $actions,
        private readonly TenantContext $tenants,
        private readonly RowDuplicates $duplicates,
    ) {}

    public function available(): bool
    {
        return (bool) auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::CATALOG);
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $table = ['type' => 'integer', 'description' => 'Id of the table (the "id" list_tables gives).'];
        $values = [
            'type' => 'object',
            'description' => 'Values by column label (or key): {"Ամսաթիվ": "2026-10-27", "Ապրանք": "Մոմ", "Գումար": 4500}. Dates as YYYY-MM-DD (datetime: YYYY-MM-DD HH:MM), numbers without spaces, yes/no as true/false, a select column one of its options.',
            'additionalProperties' => true,
        ];
        $column = [
            'type' => 'object',
            'properties' => [
                'label' => ['type' => 'string', 'description' => 'The column\'s name, in the owner\'s language.'],
                'type' => ['type' => 'string', 'enum' => DataColumnType::values()],
                'role' => ['type' => ['string', 'null'], 'enum' => [...ColumnRole::values(), null], 'description' => 'What the column means (see "meanings" of list_tables): date, item, quantity, unit_price, amount, cost, performer, channel, category... Each meaning on one column only, of a type it allows (date: date/datetime; money ones: money/number). Leave out to guess it from the label; null for none.'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'For a select column: its choices.'],
            ],
            'required' => ['label', 'type'],
        ];
        $ref = ['type' => 'string', 'description' => 'The column\'s label (or key).'];

        return [
            [
                'name' => self::ROWS,
                'description' => 'Reads rows of one table with their ids (to edit or delete them), newest first, and the table\'s columns (key, label, type, meaning). Filter by dates, words or ids. The values of columns about buyers (names, phones, addresses) are not shown to you ("hidden"), but you write them in add_rows and update_rows exactly as the owner dictates.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'table' => $table,
                        'search' => ['type' => 'string', 'description' => 'Words a row must contain.'],
                        'from' => ['type' => 'string', 'description' => 'First day, YYYY-MM-DD.'],
                        'to' => ['type' => 'string', 'description' => 'Last day, YYYY-MM-DD.'],
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'limit' => ['type' => 'integer', 'description' => '1-100, default 30.'],
                    ],
                    'required' => ['table'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::DUPLICATES,
                'description' => 'Finds the same order, sale or expense written in two or more tables (rows of one day whose fields agree). kind "exact": every filled field is the same; the copy ("copyOf" = the row kept) is already counted once in the calendar, analytics and reports. kind "near": nothing differs but one row lacks a field the other has (an address, a time): both still count. Rows with another performer, price or time are different records and never listed. Show the owner what you found and let them decide: delete a copy (delete_rows with its id and table, one card per table) or keep both (they press «Թողնել երկուսն էլ» on the Duplicates page, /admin/duplicates).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => ['type' => 'string', 'enum' => array_map(fn (DataTableSection $s) => $s->value, RowDuplicates::SECTIONS)],
                        'limit' => ['type' => 'integer', 'description' => '1-50 groups, default 20.'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::CREATE,
                'description' => 'Prepares a new table: its section, name, columns (each with a type and, where it fits, a meaning) and, if the owner gave them, its first rows (up to '.TableChanges::MAX_ROWS.'). An orders, sales or expenses table needs a date column (role date) so its rows count in analytics, and an amount or unit_price column for money. Nothing is made until the owner presses the card\'s button.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'section' => ['type' => 'string', 'enum' => array_column(DataTableSection::cases(), 'value'), 'description' => 'orders (services done), sales (goods sold) or expenses.'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'period' => ['type' => 'string', 'enum' => TablePeriod::values(), 'description' => 'What stretch of time it is kept for (a month\'s table...); leave out for any dates.'],
                        'period_date' => ['type' => 'string', 'description' => 'A day inside that period, YYYY-MM-DD (default today).'],
                        'columns' => ['type' => 'array', 'items' => $column],
                        'rows' => ['type' => 'array', 'items' => $values],
                    ],
                    'required' => ['section', 'name', 'columns'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::EDIT,
                'description' => 'Prepares changes of one table: its name, description or period, columns added, renamed, given a meaning (or none), the choices of a select column, columns removed (their values are lost). Several changes in one call make one card.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'table' => $table,
                        'name' => ['type' => 'string'],
                        'description' => ['type' => ['string', 'null']],
                        'period' => ['type' => ['string', 'null'], 'enum' => [...TablePeriod::values(), null]],
                        'period_date' => ['type' => 'string'],
                        'add_columns' => ['type' => 'array', 'items' => $column],
                        'rename_columns' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['column' => $ref, 'label' => ['type' => 'string']], 'required' => ['column', 'label']]],
                        'set_roles' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['column' => $ref, 'role' => ['type' => ['string', 'null'], 'enum' => [...ColumnRole::values(), null]]], 'required' => ['column', 'role']]],
                        'set_options' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['column' => $ref, 'options' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['column', 'options']]],
                        'remove_columns' => ['type' => 'array', 'items' => $ref],
                    ],
                    'required' => ['table'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::DELETE_TABLES,
                'description' => 'Prepares deleting whole tables with all their rows (of one section, up to '.TableChanges::MAX_TABLES.'). This can NOT be undone: only when the owner clearly asks; suggest downloading them first.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['tables' => ['type' => 'array', 'items' => ['type' => 'integer']]],
                    'required' => ['tables'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::ADD,
                'description' => 'Prepares adding rows to a table (up to '.TableChanges::MAX_ROWS.'), e.g. orders or expenses the owner dictated, pasted or attached as a file. A row without a date gets today\'s. Every detail the owner gave goes in, contacts too (client\'s name, phone, address: as dictated): when the table has no column for one, add it with new_columns in the same card, never drop it. Orders and sales go to the table of their date\'s period, like the calendar does: a November order given for the October table lands in November\'s table, made like October\'s when there is none (the card says so); rows of different periods are refused, so send one add_rows per period. Leave the order number empty unless the owner gave one: the next number is put in. An order with a performer comes back with "performersAway" when the performer isn\'t free then (away that day, or on another order at that time, an order lasting its duration or an hour): tell the owner and offer who is free.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'table' => $table,
                        'rows' => ['type' => 'array', 'items' => $values],
                        'new_columns' => ['type' => 'array', 'items' => $column, 'description' => 'Columns to add to the table with these rows (e.g. Հասցե, Հեռախոս, Հաճախորդ), for details it has no column for; rows then use their labels.'],
                    ],
                    'required' => ['table', 'rows'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::UPDATE,
                'description' => 'Prepares editing rows of a table (up to '.TableChanges::MAX_ROWS.'): each row by its id (from table_rows) with only the values that change.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'table' => $table,
                        'rows' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'values' => $values], 'required' => ['id', 'values']]],
                    ],
                    'required' => ['table', 'rows'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::DELETE_ROWS,
                'description' => 'Prepares deleting rows of a table by their ids (from table_rows), up to '.TableChanges::MAX_DELETE.'.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['table' => $table, 'ids' => ['type' => 'array', 'items' => ['type' => 'integer']]],
                    'required' => ['table', 'ids'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function run(string $name, array $input): array
    {
        if (! $this->available()) {
            throw new InvalidArgumentException('Only the owner or an admin of the business may do this.');
        }

        if ($name === self::ROWS) {
            return $this->rows($input);
        }
        if ($name === self::DUPLICATES) {
            return $this->findDuplicates($input);
        }

        $user = auth()->user();
        $change = match ($name) {
            self::CREATE => $this->changes->planCreate($input, $user),
            self::EDIT => $this->changes->planEdit($this->table($input), $input, $user),
            self::DELETE_TABLES => $this->changes->planDelete($this->tables($input), $user),
            self::ADD => $this->changes->planAdd($this->table($input), array_values((array) ($input['rows'] ?? [])), $user, array_values((array) ($input['new_columns'] ?? []))),
            self::UPDATE => $this->changes->planUpdate($this->table($input), array_values((array) ($input['rows'] ?? [])), $user),
            self::DELETE_ROWS => $this->changes->planDeleteRows($this->table($input), (array) ($input['ids'] ?? []), $user),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };

        $this->actions->add(['type' => 'change', 'id' => $change->id]);
        $card = $this->changes->describe($change);

        $away = $this->changes->performersAway($change);

        return array_filter([
            'planned' => true,
            'note' => 'Nothing is changed yet: the owner sees this as a card under your answer, value by value, and applies it with «Կիրառել» (or not).'
                .($change->kind === TableChange::DELETE_TABLES ? ' Deleting tables can\'t be undone.' : ' It can be undone afterwards while nobody touched what it changed.'),
            'card' => [
                'title' => $card['title'],
                'changes' => $card['changes'],
                'rows' => $card['rowsTotal'],
            ],
            // Also on the card: tell the owner and offer someone free (the HR person knows who).
            'performersAway' => $away ?: null,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private function rows(array $input): array
    {
        $table = $this->table($input, archived: true);
        $columns = collect($table->columnDefinitions());
        $shown = $columns->reject(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c));
        $limit = max(1, min(100, (int) ($input['limit'] ?? 30)));
        $date = fn (string $key) => isset($input[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$key]) ? (string) $input[$key] : null;
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) ($input['search'] ?? '')))) ?: []));

        $query = $table->rows()
            ->when($input['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', array_map('intval', (array) $ids)))
            ->when($date('from'), fn ($q, $from) => $q->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($date('to'), fn ($q, $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('occurred_at')->orderByDesc('id');

        // Words are looked for in the visible columns only (never in the buyers' details).
        $text = fn (DataTableRow $row) => mb_strtolower(implode(' ', array_map(
            fn (DataColumn $c) => is_scalar($row->data[$c->key] ?? null) ? (string) $row->data[$c->key] : '',
            $shown->all(),
        )));
        $rows = $query->limit($words === [] ? $limit : 5000)->get()
            ->filter(fn (DataTableRow $row) => collect($words)->every(fn (string $w) => str_contains($text($row), $w)));

        return [
            'table' => ['id' => $table->id, 'name' => $table->name, 'section' => $table->section->value, 'archived' => $table->archived_at !== null],
            'columns' => $columns->map(fn (DataColumn $c) => array_filter([
                'key' => $c->key,
                'label' => $c->label,
                'type' => $c->type->value,
                'meaning' => $c->role?->value,
                'options' => $c->options ?: null,
                'hidden' => TableAnalytics::isPersonalColumn($c) ?: null,
            ], fn ($v) => $v !== null))->values()->all(),
            'total' => $table->rows()->count(),
            'matched' => $rows->count(),
            'rows' => $rows->take($limit)->map(fn (DataTableRow $row) => [
                'id' => $row->id,
                'values' => $shown->mapWithKeys(fn (DataColumn $c) => [$c->label => $row->data[$c->key] ?? null])
                    ->filter(fn ($v) => $v !== null && $v !== '')->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function findDuplicates(array $input): array
    {
        $section = DataTableSection::tryFrom((string) ($input['section'] ?? ''));
        $limit = max(1, min(50, (int) ($input['limit'] ?? 20)));
        $groups = $this->duplicates->review($section, $limit, null, DataTableController::sectionAllowed(...));

        // Never the buyers' details: by label, of the tables in the groups.
        $hidden = DataTable::withArchived()
            ->whereIn('id', collect($groups)->flatMap(fn ($g) => array_column($g['rows'], 'tableId'))->unique()->all())
            ->get()
            ->mapWithKeys(fn (DataTable $t) => [$t->id => collect($t->columnDefinitions())
                ->filter(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c))
                ->pluck('label')->all()]);

        return [
            'groups' => array_map(fn (array $group) => [
                'day' => $group['day'],
                'section' => $group['section'],
                'kind' => $group['kind'],
                'rows' => array_map(fn (array $row) => array_filter([
                    'id' => $row['id'],
                    'table' => ['id' => $row['tableId'], 'name' => $row['table']],
                    'time' => $row['time'],
                    'amount' => $row['amount'],
                    'fromGoogle' => $row['source'] === DataTableRow::SOURCE_GOOGLE ?: null,
                    'copyOf' => $row['copyOf'],
                    'values' => array_filter(
                        array_diff_key($row['values'], array_flip($hidden[$row['tableId']] ?? [])),
                        fn ($v) => $v !== null && $v !== '',
                    ),
                    'lacks' => $row['missing'] ?: null,
                ], fn ($v) => $v !== null), $group['rows']),
            ], $groups),
            'note' => $groups === [] ? 'No duplicates between tables.' : 'Rows of a Google table come back on the next update from Google if deleted: delete the other copy instead.',
        ];
    }

    private function table(array $input, bool $archived = false): DataTable
    {
        $table = ($archived ? DataTable::withArchived() : DataTable::query())->find((int) ($input['table'] ?? 0));
        if (! $table || ! DataTableController::sectionAllowed($table->section)) {
            throw new InvalidArgumentException('No such table (call list_tables for the ids).');
        }

        return $table;
    }

    /** @return Collection<int, DataTable> */
    private function tables(array $input): Collection
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($input['tables'] ?? []))));
        $tables = DataTable::withArchived()->whereIn('id', $ids)->get()
            ->filter(fn (DataTable $t) => DataTableController::sectionAllowed($t->section));
        if ($ids === [] || $tables->count() !== count($ids)) {
            throw new InvalidArgumentException('Some of these tables don\'t exist (call list_tables for the ids).');
        }

        return $tables;
    }
}
