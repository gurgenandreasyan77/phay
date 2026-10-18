<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\MonthTables;
use App\DataTables\RowMetrics;
use App\Enums\DataTableSection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Http\Resources\Admin\DataTableRowResource;
use App\Models\DataTable;
use App\Models\DataTableRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * An order added from the calendar. It goes to the table of its date's
 * month (see MonthTables): a September order chosen for the October table
 * lands in the September one, made like the October table when there is
 * none yet. A table that isn't a month's table keeps every order.
 */
class CalendarRowController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'integer'],
            'values' => ['present', 'array'],
        ]);

        $chosen = DataTable::query()->where('section', DataTableSection::Orders)->findOrFail($validated['table']);
        abort_if($chosen->dateColumn() === null, 422, 'The table has no date column.');

        $values = $validated['values'];
        $data = DataTableRowController::parseValues($chosen, $values, fillDate: true);
        // The date as read (or "now" when left empty), for whichever table the row goes to.
        $values[$chosen->dateColumn()->key] = $data[$chosen->dateColumn()->key];
        $month = substr((string) RowMetrics::for($chosen)($data)['occurred_at'], 0, 7);

        [$row, $table, $created] = DB::transaction(function () use ($request, $chosen, $values, $data, $month) {
            [$table, $created] = MonthTables::forMonth($chosen, $month);

            if ($table->isNot($chosen)) {
                [$carried, $origins] = MonthTables::carry($chosen, $table, $values);
                // A table made otherwise: its errors are shown on the form's fields.
                $data = DataTableRowController::parseValues($table, $carried, errorKeys: $origins);
            }

            $row = $table->rows()->make([
                'data' => $data,
                'source' => DataTableRow::SOURCE_MANUAL,
                'created_by_id' => $request->user()->id,
            ]);
            $row->setRelation('table', $table)->save();

            return [$row, $table, $created];
        });

        return response()->json(['data' => [
            'row' => (new DataTableRowResource($row))->resolve($request),
            'table' => (new DataTableResource($table))->resolve($request),
            // A new table of the month, made for this order.
            'created' => $created,
        ]], 201);
    }
}
