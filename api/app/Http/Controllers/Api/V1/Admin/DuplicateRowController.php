<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\Duplicates\RowDuplicates;
use App\Enums\DataTableSection;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The same record in several tables (RowDuplicates): the groups to look
 * at, and the owner's answer to each: keep all (they are different
 * records) or delete the copies chosen.
 */
class DuplicateRowController extends Controller
{
    public function __construct(private readonly RowDuplicates $duplicates) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['sometimes', 'nullable', Rule::enum(DataTableSection::class)],
        ]);
        $section = isset($validated['section']) ? DataTableSection::from($validated['section']) : null;
        abort_if($section !== null && ! DataTableController::sectionAllowed($section), 404);

        $groups = $this->duplicates->review($section, 100, null, $this->allowed(...));

        return response()->json(['data' => [
            'groups' => $groups,
            // Rows left out of the counts as copies.
            'copies' => collect($groups)->sum(fn (array $g) => collect($g['rows'])->whereNotNull('copyOf')->count()),
        ]]);
    }

    /** «Keep all»: different records, never counted once or asked about again. */
    public function keep(Request $request): JsonResponse
    {
        $ids = $this->rowIds($request);
        abort_if(count($ids) < 2 || count($ids) > 50, 422, 'Choose 2 to 50 rows.');

        $this->duplicates->dismiss($ids, $request->user()->id);

        return response()->json(['data' => ['kept' => count($ids)]]);
    }

    /** Deletes the copies chosen (at least one row of the group stays: the page sends the others). */
    public function destroy(Request $request): JsonResponse
    {
        $ids = $this->rowIds($request);

        $rows = DataTableRow::query()->whereIn('id', $ids)->get();
        RowDuplicates::paused(fn () => $rows->each->delete());
        $this->duplicates->refresh();

        return response()->json(['data' => ['deleted' => $rows->count()]]);
    }

    /** @return list<int> rows of tables of sections the person works with */
    private function rowIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $rows = DataTableRow::query()->whereIn('id', $validated['ids'])->get(['id', 'data_table_id']);
        $tables = DataTable::withArchived()->whereIn('id', $rows->pluck('data_table_id')->unique())->get()->keyBy('id');
        abort_if($rows->count() !== count(array_unique($validated['ids'])), 404);
        foreach ($rows as $row) {
            abort_unless($this->allowed($tables[$row->data_table_id]->section), 404);
        }

        return $rows->modelKeys();
    }

    private function allowed(DataTableSection $section): bool
    {
        return DataTableController::sectionAllowed($section);
    }
}
