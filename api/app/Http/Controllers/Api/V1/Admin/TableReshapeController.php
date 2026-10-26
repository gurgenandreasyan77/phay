<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\Reshape\ReshapeRefused;
use App\DataTables\Reshape\TableReshaper;
use App\Enums\TablePeriod;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\TableReshape;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Splitting tables into periods and putting them together (TableReshaper):
 * a preview first, then the reshape itself, and undoing it while the new
 * tables are untouched. A reshape an AI agent planned in the chat is
 * applied here by the owner. Owner and admin (they design the tables);
 * expenses tables only for those who see them.
 */
class TableReshapeController extends Controller
{
    public function preview(Request $request, TableReshaper $reshaper): JsonResponse
    {
        [$tables, $kind, $undated] = $this->input($request);

        return $this->refusing(fn () => response()->json(['data' => $reshaper->preview($tables, $kind, $undated)]));
    }

    public function store(Request $request, TableReshaper $reshaper): JsonResponse
    {
        [$tables, $kind, $undated] = $this->input($request);

        return $this->refusing(fn () => response()->json([
            'data' => $reshaper->describe($reshaper->run($tables, $kind, $undated, $request->user())),
        ], 201));
    }

    public function show(TableReshape $tableReshape, TableReshaper $reshaper): JsonResponse
    {
        $this->ensureReachable($tableReshape);

        return response()->json(['data' => $reshaper->describe($tableReshape)]);
    }

    public function apply(TableReshape $tableReshape, TableReshaper $reshaper): JsonResponse
    {
        $this->ensureReachable($tableReshape);

        return $this->refusing(fn () => response()->json(['data' => $reshaper->describe($reshaper->apply($tableReshape))]));
    }

    public function undo(TableReshape $tableReshape, TableReshaper $reshaper): JsonResponse
    {
        $this->ensureReachable($tableReshape);

        return $this->refusing(fn () => response()->json(['data' => $reshaper->describe($reshaper->undo($tableReshape))]));
    }

    /**
     * The chosen tables (every one of them reachable, or 404), the period
     * of the new tables and where rows without a date go.
     *
     * @return array{0: Collection<int, DataTable>, 1: TablePeriod, 2: string}
     */
    private function input(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.TableReshaper::MAX_TABLES],
            'ids.*' => ['integer', 'distinct'],
            'period' => ['required', Rule::enum(TablePeriod::class)],
            'undated' => ['nullable', Rule::in(TableReshape::UNDATED)],
        ]);

        $tables = DataTable::withArchived()->whereIn('id', $validated['ids'])->get();
        abort_unless($tables->count() === count($validated['ids']), 404);
        $tables->each(fn (DataTable $table) => DataTableController::ensureReachable($table));

        return [$tables, TablePeriod::from($validated['period']), $validated['undated'] ?? TableReshape::UNDATED_SEPARATE];
    }

    private function ensureReachable(TableReshape $reshape): void
    {
        abort_unless(DataTableController::sectionAllowed($reshape->section), 404);
    }

    /** @param  Closure(): JsonResponse  $action */
    private function refusing(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (ReshapeRefused $e) {
            throw new ApiException(422, 'reshape_refused', $e->getMessage(), $e);
        }
    }
}
