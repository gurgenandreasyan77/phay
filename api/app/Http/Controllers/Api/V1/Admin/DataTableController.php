<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataColumn;
use App\DataTables\DataTableSchema;
use App\DataTables\Import\ColumnMatcher;
use App\DataTables\Period;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\DataTable;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The business's own tables (orders, sales, expenses): their names and
 * columns. A table is only reachable by roles that may see its section.
 */
class DataTableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['section' => ['nullable', Rule::enum(DataTableSection::class)]]);

        $sections = collect(DataTableSection::cases())
            ->filter(fn (DataTableSection $section) => self::sectionAllowed($section))
            ->when($request->filled('section'), fn ($sections) => $sections->filter(
                fn (DataTableSection $section) => $section->value === $request->input('section'),
            ))
            ->map(fn (DataTableSection $section) => $section->value)
            ->values();

        // Older versions too, after the current ones.
        $tables = DataTable::withArchived()
            ->whereIn('section', $sections)
            ->withCount(['rows', 'rows as rows_without_date_count' => fn ($q) => $q->whereNull('occurred_at')])
            ->withMin('rows', 'occurred_at')
            ->withMax('rows', 'occurred_at')
            ->withSum('rows', 'amount')
            ->withMax('imports', 'created_at')
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return DataTableResource::collection(PeriodTables::infer($tables));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['required', Rule::enum(DataTableSection::class)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...self::periodRules(),
            ...DataTableSchema::rules(),
        ]);

        $section = DataTableSection::from($validated['section']);
        abort_unless(self::sectionAllowed($section), 404);

        $table = DataTable::create([
            'section' => $section,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'columns' => DataTableSchema::columns($validated['columns']),
            ...self::periodFields($validated),
            'sort_order' => (DataTable::query()->where('section', $section)->max('sort_order') ?? 0) + 1,
        ]);
        PeriodTables::infer(collect([$table]));

        return (new DataTableResource($table->loadCount('rows')))->response()->setStatusCode(201);
    }

    public function show(DataTable $dataTable): DataTableResource
    {
        self::ensureReachable($dataTable);
        PeriodTables::infer(collect([$dataTable]));

        return new DataTableResource($dataTable->loadCount('rows'));
    }

    public function update(Request $request, DataTable $dataTable): DataTableResource
    {
        self::ensureReachable($dataTable);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ...self::periodRules(),
            ...collect(DataTableSchema::rules())
                ->map(fn (array $rules, string $field) => $field === 'columns' ? ['sometimes', ...$rules] : $rules)
                ->all(),
        ]);

        if (array_key_exists('columns', $validated)) {
            $validated['columns'] = DataTableSchema::columns($validated['columns'], $dataTable);
        }

        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        $dataTable->fill([...collect($validated)->except(['period', 'periodDate'])->all(), ...self::periodFields($validated, $dataTable)]);
        $recompute = $dataTable->isDirty('columns');
        $dataTable->save();

        // A new date, price, quantity or performer column (or a retyped one):
        // every row is read again, so analytics follow at once.
        if ($recompute) {
            RowMetrics::recompute($dataTable);
        }

        $fresh = $dataTable->fresh();
        PeriodTables::infer(collect([$fresh]));

        return new DataTableResource($fresh->loadCount('rows'));
    }

    /**
     * Roles for the columns that have none, read from their names and from
     * the values already in the table ("iPhone 15" → model, "Китай" →
     * country), for the designer to offer: the person checks and saves.
     * Only roles the column's type allows and no other column has.
     */
    public function roleSuggestions(DataTable $dataTable): JsonResponse
    {
        self::ensureReachable($dataTable);

        $columns = $dataTable->columnDefinitions();
        $taken = collect($columns)->filter(fn (DataColumn $c) => $c->role !== null)->mapWithKeys(fn (DataColumn $c) => [$c->role->value => true])->all();
        $rows = $dataTable->rows()->latest('id')->limit(200)->pluck('data');
        $suggestions = [];

        foreach ($columns as $column) {
            if ($column->role !== null) {
                continue;
            }

            $values = $rows->map(fn ($data) => $data[$column->key] ?? null)
                ->filter(fn ($v) => is_scalar($v) && ! is_bool($v))
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
            $role = ColumnMatcher::roleOf($column->label, $values, $column->type, $dataTable->section, $taken);

            if ($role && in_array($column->type, $role->types(), true)) {
                $suggestions[] = ['key' => $column->key, 'role' => $role->value];
                $taken[$role->value] = true;
            }
        }

        return response()->json(['data' => $suggestions]);
    }

    public function destroy(DataTable $dataTable): Response
    {
        self::ensureReachable($dataTable);

        $dataTable->delete();

        return response()->noContent();
    }

    /** Deletes several tables at once (e.g. a month of daily files), with their rows and imports. */
    public function bulkDestroy(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $tables = DataTable::withArchived()->whereIn('id', $validated['ids'])->get();
        $tables->each(fn (DataTable $table) => self::ensureReachable($table));
        $tables->each->delete();

        return response()->noContent();
    }

    /** A table's period, and a day in it (the first day is stored). */
    private static function periodRules(): array
    {
        return [
            'period' => ['sometimes', 'nullable', Rule::enum(TablePeriod::class)],
            'periodDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * The period fields to store from a request: the chosen kind and the
     * first day of the period the given day is in; without a day, the one
     * the table already holds, else today's. Nothing when the request
     * doesn't choose a period.
     *
     * @return array{period?: ?TablePeriod, period_start?: ?CarbonImmutable}
     */
    private static function periodFields(array $validated, ?DataTable $table = null): array
    {
        if (! array_key_exists('period', $validated)) {
            return [];
        }

        $kind = $validated['period'] === null ? null : TablePeriod::from($validated['period']);
        if ($kind === null || ! $kind->isCalendar()) {
            return ['period' => $kind, 'period_start' => null];
        }

        if ($table !== null) {
            PeriodTables::infer(collect([$table]));
        }
        $day = $validated['periodDate']
            ?? $table?->period()?->start
            ?? now(app(TenantContext::class)->current()->timezone ?: config('app.timezone'))->toDateString();

        return ['period' => $kind, 'period_start' => Period::of($kind, $day)->start];
    }

    /** The person's role may see the section (expenses: the owner). */
    public static function sectionAllowed(DataTableSection $section): bool
    {
        return $section->allows(auth()->user(), $section->permission());
    }

    /** A table of a section the role doesn't see behaves as if it doesn't exist. */
    public static function ensureReachable(DataTable $table): void
    {
        abort_unless(self::sectionAllowed($table->section), 404);
    }
}
