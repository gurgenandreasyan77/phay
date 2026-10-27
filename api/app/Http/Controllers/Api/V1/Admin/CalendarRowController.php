<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\MonthTables;
use App\DataTables\OrderNumbers;
use App\DataTables\PerformerAvailability;
use App\DataTables\Period;
use App\DataTables\PeriodTables;
use App\DataTables\RowMetrics;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableResource;
use App\Http\Resources\Admin\DataTableRowResource;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An order (or a sale) added from the calendar. It goes to the table of its date's
 * period (see PeriodTables): a September order chosen for the October table
 * lands in the September one, made like the October table when there is
 * none yet; the same for tables of a day, week, quarter or year. A table of
 * any dates keeps every order.
 *
 * An empty order number gets the next one (OrderNumbers). A performer who
 * is away that day, or on another order at that time, is told (409
 * performer_busy, with who is free) unless the owner saves it anyway.
 */
class CalendarRowController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'integer'],
            'values' => ['present', 'array'],
            // Saved though the performer isn't free (they were told).
            'anyway' => ['sometimes', 'boolean'],
        ]);

        $chosen = $this->chosen($validated['table']);
        $values = $validated['values'];
        // Left empty: numbered once the business's orders are locked (below).
        $number = $chosen->roleColumn(ColumnRole::OrderNumber);
        $numbered = $number && trim((string) ($values[$number->key] ?? '')) === '';
        if ($numbered) {
            $values = OrderNumbers::fill($chosen, [$values])[0];
        }
        $data = DataTableRowController::parseValues($chosen, $values, fillDate: true);
        // The date as read (or "now" when left empty), for whichever table the row goes to.
        $values[$chosen->dateColumn()->key] = $data[$chosen->dateColumn()->key];
        $date = substr((string) RowMetrics::for($chosen)($data)['occurred_at'], 0, 10);
        $anyway = (bool) ($validated['anyway'] ?? false);

        [$row, $table, $created] = DB::transaction(function () use ($request, $chosen, $values, $data, $date, $numbered, $anyway) {
            // One at a time per business: two quick orders of a new period make one table.
            Tenant::query()->whereKey(app(TenantContext::class)->id())->lockForUpdate()->first();

            [$table, $created] = PeriodTables::forDate($chosen, $date);

            if ($table->isNot($chosen)) {
                [$carried, $origins] = MonthTables::carry($chosen, $table, $values);
                // A table made otherwise: its errors are shown on the form's fields.
                $data = DataTableRowController::parseValues($table, $carried, errorKeys: $origins);
            }
            if ($numbered && $key = $table->roleColumn(ColumnRole::OrderNumber)?->key) {
                $data[$key] = null;
                $data = OrderNumbers::fill($table, [$data])[0];
            }

            // Nothing is kept (not even a new table) while the owner decides.
            $conflicts = PerformerAvailability::check($table, $data);
            if ($conflicts !== [] && ! $anyway) {
                throw new HttpResponseException(response()->json([
                    'message' => implode(' ', array_column($conflicts, 'message')),
                    'code' => 'performer_busy',
                    'conflicts' => $conflicts,
                ], 409));
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
            // A new table of the period, made for this order.
            'created' => $created,
            // The year, quarter, month, ISO week and day the order is filed under.
            'period' => Period::tags($date),
        ]], 201);
    }

    /**
     * What the form shows while it's filled in: whether the performer is
     * free then, and the number an empty order number will get. Nothing is
     * saved; values that don't read yet just say nothing.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'integer'],
            'values' => ['present', 'array'],
        ]);

        $table = $this->chosen($validated['table']);
        try {
            $data = DataTableRowController::parseValues($table, $validated['values'], fillDate: true);
        } catch (ValidationException) {
            $data = null;
        }

        return response()->json(['data' => [
            'conflicts' => $data === null ? [] : PerformerAvailability::check($table, $data),
            'orderNumber' => OrderNumbers::next($table),
        ]]);
    }

    private function chosen(int $id): DataTable
    {
        $chosen = DataTable::query()->whereIn('section', [DataTableSection::Orders->value, DataTableSection::Sales->value])->findOrFail($id);
        DataTableController::ensureReachable($chosen);
        abort_if($chosen->dateColumn() === null, 422, 'The table has no date column.');

        return $chosen;
    }
}
