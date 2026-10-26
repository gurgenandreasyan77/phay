<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TablePeriod;
use App\Exports\Download;
use App\Exports\ReportExport;
use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Files the business's admin downloads: its own tables (one, or several
 * as sheets of one workbook) and the owner's reports (profit, expenses),
 * as XLSX or CSV. Made on the server for this request from this
 * business's data only (tenant-scoped models) and sent as an attachment;
 * nothing is kept and there is no link to share.
 */
class ExportController extends Controller
{
    /** Tables one workbook holds at most. */
    private const MAX_TABLES = 50;

    public function table(Request $request, DataTable $dataTable): BinaryFileResponse
    {
        DataTableController::ensureReachable($dataTable);

        return Download::response([TableExport::sheet($dataTable)], $dataTable->name, $this->format($request));
    }

    /** Several tables, a sheet each (XLSX only: a CSV holds one table). */
    public function tables(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'format' => ['nullable', Rule::in(Download::FORMATS)],
        ]);
        $ids = array_values(array_unique(array_map('intval', explode(',', $validated['ids']))));
        abort_if(count($ids) > self::MAX_TABLES, 422, 'At most '.self::MAX_TABLES.' tables at once.');

        $tables = DataTable::withArchived()->whereIn('id', $ids)->orderBy('section')->orderBy('period_start')->orderBy('sort_order')->orderBy('id')->get();
        abort_unless($tables->count() === count($ids), 404);
        $tables->each(fn (DataTable $table) => DataTableController::ensureReachable($table));

        $format = $validated['format'] ?? Download::XLSX;
        abort_if($format === Download::CSV && $tables->count() > 1, 422, 'A CSV file holds one table: choose XLSX for several.');
        $name = $tables->count() === 1 ? $tables->first()->name : 'Աղյուսակներ ('.$tables->count().')';

        return Download::response($tables->map(fn (DataTable $t) => TableExport::sheet($t))->all(), $name, $format);
    }

    public function finance(Request $request, ReportExport $reports): BinaryFileResponse
    {
        [$by, $date] = $this->period($request);
        ['name' => $name, 'sheets' => $sheets] = $reports->finance($by, $date);

        return Download::response($sheets, $name, $this->format($request));
    }

    public function expenses(Request $request, ReportExport $reports): BinaryFileResponse
    {
        [$by, $date] = $this->period($request);
        ['name' => $name, 'sheets' => $sheets] = $reports->expenses($by, $date);

        return Download::response($sheets, $name, $this->format($request));
    }

    private function format(Request $request): string
    {
        return $request->validate(['format' => ['nullable', Rule::in(Download::FORMATS)]])['format'] ?? Download::XLSX;
    }

    /** @return array{0: TablePeriod, 1: ?string} */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'by' => ['nullable', Rule::in(TablePeriod::calendarValues())],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [TablePeriod::from($validated['by'] ?? 'month'), $validated['date'] ?? null];
    }
}
