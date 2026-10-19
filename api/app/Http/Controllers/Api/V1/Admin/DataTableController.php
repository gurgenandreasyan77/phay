<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataTableSchema;
use App\DataTables\Period;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The business's own tables (orders, sales, expenses): their names and
 * columns. A table is only reachable while its section's module is on and
 * by roles that may see the section.
 */
class DataTableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['section' => ['nullable', Rule::enum(DataTableSection::class)]]);

        $sections = collect(DataTableSection::cases())
            ->filter(fn (DataTableSection $section) => self::moduleActive($section))
            ->when($request->filled('section'), fn ($sections) => $sections->filter(
                fn (DataTableSection $section) => $section->value === $request->input('section'),
            ))
            ->map(fn (DataTableSection $section) => $section->value)
            ->values();

        $tables = DataTable::query()
            ->whereIn('section', $sections)
            ->withCount(['rows', 'rows as rows_without_date_count' => fn ($q) => $q->whereNull('occurred_at')])
            ->withMin('rows', 'occurred_at')
            ->withMax('rows', 'occurred_at')
            ->withSum('rows', 'amount')
            ->withMax('imports', 'created_at')
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
        abort_unless(self::moduleActive($section), 404);

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

        $tables = DataTable::query()->whereIn('id', $validated['ids'])->get();
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
     * @return array{period?: ?TablePeriod, period_start?: ?\Carbon\CarbonImmutable}
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

    /** The section's module is on, and the person's role may see it (expenses: the owner). */
    public static function moduleActive(DataTableSection $section): bool
    {
        return $section->isActive() && $section->allows(auth()->user(), $section->permission());
    }

    /** A table of a module that is off behaves as if it doesn't exist. */
    public static function ensureReachable(DataTable $table): void
    {
        abort_unless(self::moduleActive($table->section), 404);
    }
}
