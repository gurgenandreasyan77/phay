<?php

namespace App\Documents;

use App\Assistant\AttachmentKind;
use App\Assistant\Attachments;
use App\DataTables\DataColumn;
use App\DataTables\TableAnalytics;
use App\Enums\BusinessRole;
use App\Enums\DataColumnType;
use App\Http\Controllers\Api\V1\Admin\DataTableController;
use App\Models\AssistantAttachment;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The documents agent's workshop: Word files written from scratch
 * (write_document), a template the owner attached filled in for one case
 * (fill_document: a dismissal agreement for this employee) and the same
 * template filled for every order of a table or every employee
 * (fill_documents_from_data), many at once, as a ZIP and as one file to
 * print. The files are kept with the answer for the person who asked
 * (AssistantAttachment origin document) and downloaded from the chat.
 *
 * Filling keeps the template's look (TemplateFiller). In a batch the
 * values come from the business's data on the server: the model maps the
 * template's phrases to columns and never reads the buyers' details.
 *
 * One instance per request (one question): it collects the files made,
 * which the chat ties to the answer.
 */
class DocumentStudio
{
    public const WRITE = 'write_document';

    public const FILL = 'fill_document';

    public const BATCH = 'fill_documents_from_data';

    public const NAMES = [self::WRITE, self::FILL, self::BATCH];

    /** Files one question may make (a batch's ZIP and print file count as two). */
    public const MAX_FILES = 8;

    /** Copies in one fill_document call; more come as a ZIP. */
    public const MAX_FILL = 20;

    /** Documents one batch makes at most. */
    public const MAX_BATCH = 200;

    /** Copies kept as separate files; past this they come as a ZIP (and one file to print). */
    private const SEPARATE = 3;

    private const MAX_WRITE_CHARS = 60_000;

    /** {Column}, {#}, {today}. */
    private const PLACEHOLDER = '/\{([^{}]{1,80})\}/u';

    /** @var list<AssistantAttachment> */
    private array $produced = [];

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TemplateFiller $filler,
        private readonly DocxWriter $writer,
    ) {}

    public function newQuestion(): void
    {
        $this->produced = [];
    }

    /** @return list<AssistantAttachment> the files made while answering this question */
    public function produced(): array
    {
        return $this->produced;
    }

    /** How the agent works with documents, for its prompt. */
    public static function guide(): string
    {
        return <<<'TEXT'
        Making documents (Word files the owner downloads from your answer):
        - Files the owner attaches (Word DOCX, OpenDocument ODT, RTF, PDF, PowerPoint PPTX, Excel XLSX, web pages, TXT) come to you as their text: a Word or ODT file one paragraph per line, a table row as "| cell | cell |", headers and footers after "[header]" / "[footer]".
        - One filled copy of a template (DOCX, ODT, TXT, MD): fill_document with the template's [file #N] and replacements copied EXACTLY from its text (each within one paragraph or one table cell, with the same spaces and punctuation; a longer unique phrase is safer than a short one). They go in order, each over the first place still there: a form with five "________" blanks is filled by five replacements in reading order; "all": true replaces every place (the client's name repeated through a contract). The template's look stays as it is. Replace example data in a sample document (another employee's name, old dates, amounts) the same way.
        - The same template for many orders, sales, expenses or employees: fill_documents_from_data. Map each phrase of the template to a value made of columns, e.g. {"find": "________", "value": "{Հաճախորդ}"} or {"find": "Акт № 15", "value": "Акт № {Պատվերի համար}"}; {#} is the copy's number, {today} today's date. The server fills each copy from the data (you don't see buyers' names and phones, and need not), makes a ZIP and, for Word, one file with all copies to print. Look up the table's id and its columns first (list_tables, table_rows), or the team (team_overview) for employees.
        - A document from scratch (a contract, act, statement, letter, power of attorney, price offer, instruction), or a template that is a PDF, picture or RTF and can't be filled in place: write_document, in the owner's language unless asked otherwise, complete, with real data you have (look it up) and "________" only where nobody gave it to you. To make many copies of a PDF form, first write_document recreating it as Word (same text, blanks as "________"), then fill_documents_from_data with that new file's [file #N].
        - Reviews: when asked what a document is about, give its purpose, parties, key terms, money, dates and deadlines, obligations and risks, in short points, quoting the document's numbers and clauses. Say what is missing or unusual. You are not a lawyer: for legal force and signatures a lawyer or notary confirms.
        - Never invent personal data (passport numbers, addresses, salaries): ask, or leave a blank. Never write links: the files appear under your answer.
        TEXT;
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $file = ['type' => 'integer', 'description' => 'The template: the N of [file #N] (a file attached in this chat or a document you made).'];
        $replacement = [
            'type' => 'object',
            'properties' => [
                'find' => ['type' => 'string', 'description' => 'Text exactly as in the template, within one paragraph or table cell.'],
                'replace' => ['type' => 'string', 'description' => 'What goes in its place ("\n" for a new line).'],
                'all' => ['type' => 'boolean', 'description' => 'Every place, not only the first one left.'],
            ],
            'required' => ['find', 'replace'],
            'additionalProperties' => false,
        ];

        return [
            [
                'name' => self::WRITE,
                'description' => 'Makes a Word (DOCX) document from your text, a file under your answer. One paragraph per line; "# " title, "## " and "### " headings; "- " list items; "1. " numbered points; "| a | b |" table rows (a "|---|---|" line under the header row); **bold**, *italic*; "[center] ", "[right] " or "[justify] " at the start of a line aligns it; "[pagebreak]"; "---" a line across; two blank lines leave an empty line (room to sign).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'The file\'s name, without the extension (e.g. «Աշխատանքից ազատման համաձայնագիր - Գուրգեն»).'],
                        'content' => ['type' => 'string', 'description' => 'The whole document.'],
                        'format' => ['type' => 'string', 'enum' => ['docx', 'txt', 'md'], 'description' => 'docx (default); txt or md only when asked.'],
                    ],
                    'required' => ['title', 'content'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::FILL,
                'description' => 'Fills a template (DOCX, ODT, TXT or MD) for one case or a few, keeping its look: each document is the template with your replacements. Up to '.self::MAX_FILL.' documents; more than '.self::SEPARATE.' come as a ZIP. Comes back with the phrases not found (nothing is made then: copy them exactly from the text and call again).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template' => $file,
                        'documents' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'description' => 'The file\'s name, without the extension.'],
                                    'replacements' => ['type' => 'array', 'items' => $replacement],
                                ],
                                'required' => ['title', 'replacements'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['template', 'documents'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::BATCH,
                'description' => 'Fills a template (DOCX, ODT, TXT or MD) once for every row of one of the business\'s tables (orders, sales, expenses; filter by dates, words or row ids) or for every employee, with values the server takes from the data: up to '.self::MAX_BATCH.' documents, as a ZIP, and for Word also one file with all of them to print. In "value" and "file_name", {Column label} is that column\'s value of the row (dates as DD.MM.YYYY, amounts with spaces), {#} the copy\'s number, {today} today\'s date. For employees the columns are {name}, {position}, {phone}, {email}, {hired_on}, {birth_date}, {group}.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template' => $file,
                        'source' => [
                            'type' => 'object',
                            'properties' => [
                                'table' => ['type' => 'integer', 'description' => 'Id of the table (list_tables).'],
                                'employees' => ['type' => 'boolean', 'description' => 'true: the team instead of a table.'],
                                'from' => ['type' => 'string', 'description' => 'Rows from this day, YYYY-MM-DD.'],
                                'to' => ['type' => 'string', 'description' => 'Rows to this day, YYYY-MM-DD.'],
                                'search' => ['type' => 'string', 'description' => 'Words a row must contain (in its visible columns).'],
                                'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Only these rows or employees.'],
                                'include_inactive' => ['type' => 'boolean', 'description' => 'Employees: also those who left.'],
                            ],
                            'additionalProperties' => false,
                        ],
                        'fields' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'find' => ['type' => 'string', 'description' => 'Text exactly as in the template.'],
                                    'value' => ['type' => 'string', 'description' => 'What goes there, with {Column} placeholders.'],
                                    'all' => ['type' => 'boolean', 'description' => 'Every place, not only the first one left.'],
                                    'empty' => ['type' => 'string', 'description' => 'What to write when the row has no value for a column used (default "____________", to fill by hand).'],
                                ],
                                'required' => ['find', 'value'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'file_name' => ['type' => 'string', 'description' => 'Each file\'s name, with placeholders, e.g. "Ակտ {Պատվերի համար}" (default: the template\'s name and {#}).'],
                        'title' => ['type' => 'string', 'description' => 'The ZIP\'s name, e.g. «Ակտեր, հոկտեմբեր 2026».'],
                    ],
                    'required' => ['template', 'source', 'fields'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function run(string $name, array $input, ?User $user): array
    {
        if (! $user) {
            throw new InvalidArgumentException('Documents can be made only in a kept chat.');
        }

        return match ($name) {
            self::WRITE => $this->write($input, $user),
            self::FILL => $this->fill($input, $user),
            self::BATCH => $this->batch($input, $user),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };
    }

    private function write(array $input, User $user): array
    {
        $content = (string) ($input['content'] ?? '');
        if (trim($content) === '') {
            throw new InvalidArgumentException('content is empty.');
        }
        if (mb_strlen($content) > self::MAX_WRITE_CHARS) {
            throw new InvalidArgumentException('The document is too long: split it in parts.');
        }
        $format = in_array($input['format'] ?? 'docx', ['docx', 'txt', 'md'], true) ? ($input['format'] ?? 'docx') : 'docx';
        $title = self::title($input['title'] ?? null, 'Փաստաթուղթ');
        $file = $this->keep($format === 'docx' ? $this->writer->write($content) : $content, $title, $format, $user);

        return ['made' => self::describe($file), 'note' => 'The owner downloads it under your answer. To fill it for many rows, use its [file #'.$file->id.'] as the template.'];
    }

    private function fill(array $input, User $user): array
    {
        [$template, $content, $extension] = $this->template($input, $user);
        $documents = array_values(array_filter((array) ($input['documents'] ?? []), 'is_array'));
        if ($documents === []) {
            throw new InvalidArgumentException('documents is empty.');
        }
        if (count($documents) > self::MAX_FILL) {
            throw new InvalidArgumentException('At most '.self::MAX_FILL.' documents at once: for many rows use '.self::BATCH.'.');
        }

        $made = [];
        $missing = [];
        foreach ($documents as $i => $document) {
            $replacements = self::replacements($document['replacements'] ?? []);
            [$filled, $notFound] = $this->filler->fill($content, $extension, $replacements);
            foreach ($notFound as $phrase) {
                $missing[$phrase][] = $i + 1;
            }
            $made[] = ['name' => self::title($document['title'] ?? null, $template->title ?? pathinfo($template->name, PATHINFO_FILENAME)).'.'.$extension, 'content' => $filled];
        }
        if ($missing !== []) {
            return [
                'made' => false,
                'not_found' => array_map(fn (string $phrase, array $docs) => ['find' => $phrase, 'documents' => $docs], array_keys($missing), $missing),
                'note' => 'Nothing was made. These phrases are not in the template (or no place of them is left): copy them exactly from its text, within one paragraph, and call again.',
            ];
        }

        return ['made' => $this->package($made, $extension, self::title($input['title'] ?? null, pathinfo($made[0]['name'], PATHINFO_FILENAME)), $user)];
    }

    private function batch(array $input, User $user): array
    {
        [$template, $content, $extension] = $this->template($input, $user);
        $fields = array_values(array_filter((array) ($input['fields'] ?? []), fn ($f) => is_array($f) && trim((string) ($f['find'] ?? '')) !== ''));
        if ($fields === []) {
            throw new InvalidArgumentException('fields is empty.');
        }
        $source = (array) ($input['source'] ?? []);
        [$records, $columns, $personal, $described] = ($source['employees'] ?? false)
            ? $this->employees($source)
            : $this->rows($source);
        if ($records === []) {
            return ['made' => false, 'note' => "Nothing to fill: {$described} has no matching rows."];
        }
        if (count($records) > self::MAX_BATCH) {
            throw new InvalidArgumentException(count($records).' rows match: at most '.self::MAX_BATCH.' documents at once. Narrow the dates.');
        }

        $base = $template->title ?: pathinfo($template->name, PATHINFO_FILENAME);
        $pattern = trim((string) ($input['file_name'] ?? '')) ?: $base.' {#}';
        $used = [];
        foreach ([...array_column($fields, 'value'), $pattern] as $text) {
            preg_match_all(self::PLACEHOLDER, (string) $text, $m);
            foreach ($m[1] as $key) {
                $used[trim($key)] = true;
            }
        }
        $known = fn (string $key) => in_array($key, ['#', 'today'], true) || array_key_exists(mb_strtolower($key), $columns);
        $unknown = array_values(array_filter(array_keys($used), fn (string $key) => ! $known($key)));
        if ($unknown !== []) {
            throw new InvalidArgumentException('No such columns: {'.implode('}, {', $unknown).'}. Columns: {'.implode('}, {', array_values($columns)).'}, and {#}, {today}.');
        }

        $today = CarbonImmutable::today()->format('d.m.Y');
        $empty = [];
        $documents = [];
        foreach (array_values($records) as $n => $record) {
            $values = array_combine(array_map('mb_strtolower', array_keys($record)), $record) + ['#' => (string) ($n + 1), 'today' => $today];
            $blanks = [];
            $render = function (string $text, string $blank) use ($values, &$blanks) {
                return preg_replace_callback(self::PLACEHOLDER, function (array $m) use ($values, $blank, &$blanks) {
                    $key = mb_strtolower(trim($m[1]));
                    $value = (string) ($values[$key] ?? '');
                    if ($value === '') {
                        $blanks[$key] = true;

                        return $blank;
                    }

                    return $value;
                }, $text) ?? $text;
            };
            $replacements = array_map(fn (array $f) => [
                'find' => (string) $f['find'],
                'replace' => $render((string) $f['value'], (string) ($f['empty'] ?? '____________')),
                'all' => (bool) ($f['all'] ?? false),
            ], $fields);
            [$filled, $notFound] = $this->filler->fill($content, $extension, $replacements);
            if ($notFound !== []) {
                return [
                    'made' => false,
                    'not_found' => $notFound,
                    'note' => 'Nothing was made. These phrases are not in the template (or no place of them is left): copy them exactly from its text and call again.',
                ];
            }
            $documents[] = ['name' => self::fileName($render($pattern, ''), $n + 1).'.'.$extension, 'content' => $filled];
            foreach (array_keys($blanks) as $key) {
                $empty[$key] = ($empty[$key] ?? 0) + 1;
            }
        }

        $title = self::title($input['title'] ?? null, $base.' ('.count($documents).')');
        $names = array_intersect(array_map('mb_strtolower', array_keys($used)), $personal) === []
            ? array_slice(array_column($documents, 'name'), 0, 10)
            : null;

        return array_filter([
            'made' => $this->package($documents, $extension, $title, $user, forceZip: true),
            'documents' => count($documents),
            'source' => $described,
            // Names of buyers stay out of the model's sight.
            'file_names' => $names,
            // Documents in which a column was empty (left as a blank to fill by hand).
            'empty_values' => $empty === [] ? null : array_combine(
                array_map(fn (string $key) => '{'.($columns[$key] ?? $key).'}', array_keys($empty)),
                array_values($empty),
            ),
        ], fn ($v) => $v !== null);
    }

    /**
     * The rows of a table as {label: text}, the columns by lower-case
     * label, the personal ones (buyers' details).
     *
     * @return array{0: list<array<string, string>>, 1: array<string, string>, 2: list<string>, 3: string}
     */
    private function rows(array $source): array
    {
        $table = DataTable::withArchived()->find((int) ($source['table'] ?? 0));
        if (! $table || ! DataTableController::sectionAllowed($table->section)) {
            throw new InvalidArgumentException('No such table (call list_tables for the ids), or give "employees": true.');
        }
        $columns = collect($table->columnDefinitions());
        $date = fn (string $key) => isset($source[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $source[$key]) ? (string) $source[$key] : null;
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim((string) ($source['search'] ?? '')))) ?: []));
        $shown = $columns->reject(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c));

        $rows = $table->rows()
            ->when($source['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', array_map('intval', (array) $ids)))
            ->when($date('from'), fn ($q, $from) => $q->where('occurred_at', '>=', $from.' 00:00:00'))
            ->when($date('to'), fn ($q, $to) => $q->where('occurred_at', '<=', $to.' 23:59:59'))
            ->orderBy('occurred_at')->orderBy('id')
            ->limit(self::MAX_BATCH * 5)
            ->get()
            ->filter(fn (DataTableRow $row) => collect($words)->every(fn (string $w) => str_contains(mb_strtolower(implode(' ', $shown->map(
                fn (DataColumn $c) => is_scalar($row->data[$c->key] ?? null) ? (string) $row->data[$c->key] : '',
            )->all())), $w)));

        $records = $rows->map(fn (DataTableRow $row) => $columns->mapWithKeys(
            fn (DataColumn $c) => [$c->label => self::format($row->data[$c->key] ?? null, $c->type)],
        )->all())->values()->all();

        return [
            $records,
            $columns->mapWithKeys(fn (DataColumn $c) => [mb_strtolower($c->label) => $c->label])->all(),
            $columns->filter(fn (DataColumn $c) => TableAnalytics::isPersonalColumn($c))->map(fn (DataColumn $c) => mb_strtolower($c->label))->values()->all(),
            "the table «{$table->name}»",
        ];
    }

    /** @return array{0: list<array<string, string>>, 1: array<string, string>, 2: list<string>, 3: string} */
    private function employees(array $source): array
    {
        if (! auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::SETTINGS)) {
            throw new InvalidArgumentException('Only the owner or an admin may make documents for the team.');
        }
        $employees = Employee::query()
            ->with('group')
            ->when(! ($source['include_inactive'] ?? false), fn ($q) => $q->where('is_active', true))
            ->when($source['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', array_map('intval', (array) $ids)))
            ->orderBy('name')
            ->get();
        $keys = ['name', 'position', 'phone', 'email', 'hired_on', 'birth_date', 'group'];

        return [
            $employees->map(fn (Employee $e) => [
                'name' => (string) $e->name,
                'position' => (string) $e->position,
                'phone' => (string) $e->phone,
                'email' => (string) $e->email,
                'hired_on' => $e->hired_on?->format('d.m.Y') ?? '',
                'birth_date' => $e->birth_date?->format('d.m.Y') ?? '',
                'group' => (string) $e->group?->name,
            ])->values()->all(),
            array_combine($keys, $keys),
            [],
            'the team',
        ];
    }

    /** A value as it is written in a document: 27.10.2026, 12 500, Այո. */
    private static function format(mixed $value, DataColumnType $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            DataColumnType::Date => self::date((string) $value, 'd.m.Y'),
            DataColumnType::DateTime => self::date((string) $value, 'd.m.Y H:i'),
            DataColumnType::Number, DataColumnType::Money => is_numeric($value)
                ? rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',')
                : (string) $value,
            DataColumnType::Boolean => $value ? 'Այո' : 'Ոչ',
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    private static function date(string $value, string $format): string
    {
        try {
            return CarbonImmutable::parse($value)->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * The template the model named, readable and fillable.
     *
     * @return array{0: AssistantAttachment, 1: string, 2: string}
     */
    private function template(array $input, User $user): array
    {
        $id = (int) ($input['template'] ?? 0);
        $file = AssistantAttachment::query()->of($user)->find($id)
            ?? throw new InvalidArgumentException("There is no [file #{$id}] in this chat.");
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if (! in_array($extension, TemplateFiller::EXTENSIONS, true)) {
            throw new InvalidArgumentException("[file #{$id}] ({$file->name}) can't be filled in place (only DOCX, ODT, TXT, MD): recreate it with ".self::WRITE.' (same text, blanks as "________") and fill that file.');
        }
        $content = Storage::disk(Attachments::DISK)->get($file->path)
            ?? throw new InvalidArgumentException("[file #{$id}] is no longer kept.");

        return [$file, $content, $extension];
    }

    /** @return list<array{find: string, replace: string, all: bool}> */
    private static function replacements(mixed $list): array
    {
        $out = [];
        foreach ((array) $list as $r) {
            if (is_array($r) && trim((string) ($r['find'] ?? '')) !== '') {
                $out[] = ['find' => (string) $r['find'], 'replace' => (string) ($r['replace'] ?? ''), 'all' => (bool) ($r['all'] ?? false)];
            }
        }
        if ($out === []) {
            throw new InvalidArgumentException('replacements is empty.');
        }

        return $out;
    }

    /**
     * The documents as files of the answer: a few each on its own; many as
     * a ZIP and, for Word, one file with all of them.
     *
     * @param  list<array{name: string, content: string}>  $documents
     * @return list<array<string, mixed>>
     */
    private function package(array $documents, string $extension, string $title, User $user, bool $forceZip = false): array
    {
        $zip = $forceZip ? count($documents) > 1 : count($documents) > self::SEPARATE;
        $count = $zip ? ($extension === 'docx' ? 2 : 1) : count($documents);
        if (count($this->produced) + $count > self::MAX_FILES) {
            throw new InvalidArgumentException('Enough files for this answer: answer with what you made.');
        }

        if (! $zip) {
            return array_map(fn (array $d) => self::describe($this->keep($d['content'], pathinfo($d['name'], PATHINFO_FILENAME), $extension, $user)), $documents);
        }

        $made = [self::describe($this->keep(TemplateFiller::zip($documents), $title, 'zip', $user, ['documents' => count($documents)]))];
        if ($extension === 'docx') {
            $made[] = self::describe($this->keep($this->filler->combine(array_column($documents, 'content')), $title.' - բոլորը մեկ ֆայլում', 'docx', $user, ['documents' => count($documents)]));
        }

        return $made;
    }

    private function keep(string $content, string $title, string $extension, User $user, ?array $meta = null): AssistantAttachment
    {
        $path = 'assistant/'.$this->tenants->id().'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk(Attachments::DISK)->put($path, $content);

        return $this->produced[] = AssistantAttachment::create([
            'user_id' => $user->id,
            'kind' => in_array($extension, ['txt', 'md'], true) ? AttachmentKind::Text : AttachmentKind::Document,
            'origin' => AssistantAttachment::DOCUMENT,
            'mime' => TemplateFiller::MIMES[$extension] ?? 'application/octet-stream',
            'name' => self::fileName($title, null).'.'.$extension,
            'title' => mb_substr($title, 0, 120),
            'format' => $extension,
            'size' => strlen($content),
            'path' => $path,
            'meta' => $meta,
        ]);
    }

    private static function describe(AssistantAttachment $file): array
    {
        return array_filter(['file' => "[file #{$file->id}]", 'name' => $file->name, 'documents' => $file->meta['documents'] ?? null]);
    }

    private static function title(mixed $title, string $fallback): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title) ?? '');

        return mb_substr($title === '' ? $fallback : $title, 0, 100);
    }

    /** A name a file system takes (Armenian and Russian letters stay). */
    private static function fileName(string $name, ?int $n): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $name) ?? '');
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', ' .');

        return mb_substr($name === '' ? 'document'.($n ? " {$n}" : '') : $name, 0, 100);
    }
}
