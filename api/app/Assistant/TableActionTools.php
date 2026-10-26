<?php

namespace App\Assistant;

use App\DataTables\Reshape\TableReshaper;
use App\Enums\BusinessRole;
use App\Enums\TablePeriod;
use App\Exports\Download;
use App\Http\Controllers\Api\V1\Admin\DataTableController;
use App\Models\DataTable;
use App\Models\TableReshape;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What the assistant may offer to do with the business's tables, never do
 * by itself: split tables into periods or put them together (a planned
 * reshape the owner applies from a card in the chat) and files to
 * download (a table, several tables, the profit or expenses report). Both
 * come back to the chat as cards (ReplyActions), under the answer.
 *
 * Only for a person who designs the business's tables (owner, admin), and
 * expenses tables only for those who see them.
 */
class TableActionTools
{
    public const RESHAPE = 'reshape_tables';

    public const DOWNLOAD = 'download_file';

    public const NAMES = [self::RESHAPE, self::DOWNLOAD];

    public function __construct(
        private readonly TableReshaper $reshaper,
        private readonly ReplyActions $actions,
        private readonly TenantContext $tenants,
    ) {}

    public function available(): bool
    {
        return (bool) auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::CATALOG);
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $ids = ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids of the tables (the "id" list_tables gives).'];
        $date = ['type' => 'string', 'description' => 'A day inside the period wanted, YYYY-MM-DD (default today).'];

        return [
            [
                'name' => self::RESHAPE,
                'description' => 'Prepares splitting tables into periods or putting several together, e.g. a year\'s table into monthly ones, twelve monthly tables into one for the year, weekly into quarterly, or several tables into one of any dates (period "custom"). Rows go by their date: only periods that have rows get a table (4 months of data → 4 tables). Columns and their meanings are kept; the old tables are archived, not deleted, and it can be undone. This does NOT change anything: it shows the owner a card with a preview and an «Apply» button under your answer. Tables of one section only. Call it when the owner asks for it; tell them what the card will make and that they apply it with the button.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tables' => $ids,
                        'period' => ['type' => 'string', 'enum' => TablePeriod::values(), 'description' => 'The period of the new tables: day, week, month, quarter, year, or custom (all into one table).'],
                        'undated' => ['type' => 'string', 'enum' => TableReshape::UNDATED, 'description' => 'Rows without a date: separate (a table of their own, default) or latest (into the newest table).'],
                    ],
                    'required' => ['tables', 'period'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::DOWNLOAD,
                'description' => 'Offers the owner a file to download, as a button under your answer: one or several of the business\'s tables (several: one XLSX workbook, a sheet each), the profit report per week/month/quarter/year (finance) or the expenses of one day/week/month/quarter/year (expenses). XLSX by default, CSV for one table or report. Never write links yourself: the button is the link.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'what' => ['type' => 'string', 'enum' => ['tables', 'finance', 'expenses']],
                        'tables' => $ids,
                        'by' => ['type' => 'string', 'enum' => TablePeriod::calendarValues(), 'description' => 'For finance and expenses: the period (default month).'],
                        'date' => $date,
                        'format' => ['type' => 'string', 'enum' => Download::FORMATS],
                    ],
                    'required' => ['what'],
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

        return match ($name) {
            self::RESHAPE => $this->reshape($input),
            self::DOWNLOAD => $this->download($input),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };
    }

    /** @return array<string, mixed> */
    private function reshape(array $input): array
    {
        $kind = TablePeriod::tryFrom((string) ($input['period'] ?? ''))
            ?? throw new InvalidArgumentException('period must be one of '.implode(', ', TablePeriod::values()).'.');
        $undated = (string) ($input['undated'] ?? TableReshape::UNDATED_SEPARATE);
        $tables = $this->tables($input);

        $reshape = $this->reshaper->keep($tables, $kind, $undated, auth()->user());
        $this->actions->add(['type' => 'reshape', 'id' => $reshape->id]);

        return [
            'planned' => true,
            'note' => 'Nothing is changed yet: the owner sees this preview as a card under your answer and applies it with its button (or not).',
            'preview' => $this->reshaper->describe($reshape),
        ];
    }

    /** @return array<string, mixed> */
    private function download(array $input): array
    {
        $format = (string) ($input['format'] ?? Download::XLSX);
        if (! in_array($format, Download::FORMATS, true)) {
            throw new InvalidArgumentException('format must be xlsx or csv.');
        }

        $what = (string) ($input['what'] ?? '');
        if ($what === 'tables') {
            $tables = $this->tables($input);
            if ($format === Download::CSV && $tables->count() > 1) {
                throw new InvalidArgumentException('A CSV file holds one table: use xlsx for several.');
            }
            $label = $tables->count() === 1 ? $tables->first()->name : $tables->count().' աղյուսակ';
            $action = ['type' => 'download', 'kind' => 'tables', 'ids' => $tables->modelKeys(), 'format' => $format, 'label' => $label];
        } elseif (in_array($what, ['finance', 'expenses'], true)) {
            if ($what === 'finance' && ! auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::INSIGHTS)) {
                throw new InvalidArgumentException('Only the owner may download the profit report.');
            }
            if (! auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::EXPENSES)) {
                throw new InvalidArgumentException('Only the owner may download this report.');
            }
            $by = TablePeriod::tryFrom((string) ($input['by'] ?? 'month'));
            if ($by === null || ! $by->isCalendar()) {
                throw new InvalidArgumentException('by must be one of '.implode(', ', TablePeriod::calendarValues()).'.');
            }
            $date = isset($input['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['date']) ? (string) $input['date'] : null;
            $label = ($what === 'finance' ? 'Շահույթ' : 'Ծախսեր').' · '.$by->label();
            $action = array_filter(['type' => 'download', 'kind' => $what, 'by' => $by->value, 'date' => $date, 'format' => $format, 'label' => $label]);
        } else {
            throw new InvalidArgumentException('what must be tables, finance or expenses.');
        }

        $this->actions->add($action);

        return ['offered' => true, 'file' => $action['label'].'.'.$format, 'note' => 'The owner downloads it with the button under your answer.'];
    }

    /** @return Collection<int, DataTable> */
    private function tables(array $input)
    {
        $ids = array_values(array_unique(array_map('intval', array_filter((array) ($input['tables'] ?? []), 'is_numeric'))));
        if ($ids === []) {
            throw new InvalidArgumentException('Pass the ids of the tables (from list_tables).');
        }
        if (count($ids) > TableReshaper::MAX_TABLES) {
            throw new InvalidArgumentException('Too many tables at once.');
        }

        $tables = DataTable::query()->whereIn('id', $ids)->get()
            ->filter(fn (DataTable $t) => DataTableController::sectionAllowed($t->section));
        if ($tables->count() !== count($ids)) {
            throw new InvalidArgumentException('Some of these tables don\'t exist (call list_tables for the ids).');
        }

        return $tables;
    }
}
