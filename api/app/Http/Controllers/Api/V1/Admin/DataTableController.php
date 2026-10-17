<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataTableSchema;
use App\DataTables\RowMetrics;
use App\Enums\DataTableSection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Models\DataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

/**
 * The business's own tables (orders, sales...): their names and columns.
 * A table is only reachable while its section's module is on.
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

        return DataTableResource::collection($tables);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['required', Rule::enum(DataTableSection::class)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...DataTableSchema::rules(),
            ...DataTableSchema::metricRules(),
        ]);

        $section = DataTableSection::from($validated['section']);
        abort_unless(self::moduleActive($section), 404);

        $table = new DataTable([
            'section' => $section,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'columns' => DataTableSchema::columns($validated['columns']),
            'sort_order' => (DataTable::query()->where('section', $section)->max('sort_order') ?? 0) + 1,
        ]);
        DataTableSchema::applyMetrics($table, $validated);
        $table->save();

        return (new DataTableResource($table->loadCount('rows')))->response()->setStatusCode(201);
    }

    public function show(DataTable $dataTable): DataTableResource
    {
        self::ensureReachable($dataTable);

        return new DataTableResource($dataTable->loadCount('rows'));
    }

    public function update(Request $request, DataTable $dataTable): DataTableResource
    {
        self::ensureReachable($dataTable);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ...collect(DataTableSchema::rules())
                ->map(fn (array $rules, string $field) => $field === 'columns' ? ['sometimes', ...$rules] : $rules)
                ->all(),
            ...DataTableSchema::metricRules(),
        ]);

        if (array_key_exists('columns', $validated)) {
            $validated['columns'] = DataTableSchema::columns($validated['columns'], $dataTable);
        }

        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        $dataTable->fill(collect($validated)->except(['date_column', 'amount_column', 'quantity_column'])->all());
        DataTableSchema::applyMetrics($dataTable, $validated);
        $recompute = $dataTable->isDirty(['columns', 'date_column', 'amount_column', 'quantity_column']);
        $dataTable->save();

        // New date or amount column (or a retyped one): every row is read again,
        // so analytics follow at once.
        if ($recompute) {
            RowMetrics::recompute($dataTable);
        }

        return new DataTableResource($dataTable->fresh()->loadCount('rows'));
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

    public static function moduleActive(DataTableSection $section): bool
    {
        return Feature::active($section->feature()->value);
    }

    /** A table of a module that is off behaves as if it doesn't exist. */
    public static function ensureReachable(DataTable $table): void
    {
        abort_unless(self::moduleActive($table->section), 404);
    }
}
