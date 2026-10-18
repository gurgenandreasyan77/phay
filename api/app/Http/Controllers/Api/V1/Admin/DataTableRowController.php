<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataTableSchema;
use App\DataTables\Period;
use App\Enums\DataColumnType;
use App\Enums\TablePeriod;
use Illuminate\Validation\Rule;
use App\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DataTableRowResource;
use App\Models\DataTable;
use App\Models\DataTableRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Rows of a business's own table, typed in by hand in the admin. Values are
 * keyed by column key and parsed by the column's type (DataColumn::parse).
 */
class DataTableRowController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request, DataTable $dataTable): AnonymousResourceCollection
    {
        DataTableController::ensureReachable($dataTable);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            // Rows of one day, ISO week, month, quarter or year: the one `date` is in.
            'period' => ['nullable', Rule::in(TablePeriod::calendarValues())],
            'date' => ['required_with:period', 'nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim((string) $request->input('search'));
        $period = $request->filled('period') ? Period::of(TablePeriod::from($request->input('period')), $request->input('date')) : null;

        $rows = $dataTable->rows()
            ->when($search !== '', fn ($query) => $query->whereRaw(
                'LOWER(CAST(data AS TEXT)) LIKE ?',
                ['%'.mb_strtolower(addcslashes($search, '%_\\')).'%'],
            ))
            ->when($period !== null, fn ($query) => $query
                ->where('occurred_at', '>=', $period->start->format('Y-m-d H:i:s'))
                ->where('occurred_at', '<', $period->next()->format('Y-m-d H:i:s')))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return DataTableRowResource::collection($rows);
    }

    public function store(Request $request, DataTable $dataTable): JsonResponse
    {
        DataTableController::ensureReachable($dataTable);

        $row = $dataTable->rows()->make([
            'data' => $this->validatedData($request, $dataTable, fillDate: true),
            'source' => DataTableRow::SOURCE_MANUAL,
            'created_by_id' => $request->user()->id,
        ]);
        $row->setRelation('table', $dataTable)->save();

        return (new DataTableRowResource($row))->response()->setStatusCode(201);
    }

    public function update(Request $request, DataTable $dataTable, DataTableRow $row): DataTableRowResource
    {
        DataTableController::ensureReachable($dataTable);

        $row->setRelation('table', $dataTable)->update(['data' => $this->validatedData($request, $dataTable)]);

        return new DataTableRowResource($row);
    }

    public function destroy(DataTable $dataTable, DataTableRow $row): Response
    {
        DataTableController::ensureReachable($dataTable);

        $row->delete();

        return response()->noContent();
    }

    /**
     * @param  bool  $fillDate  a new row without a date happened now
     */
    private function validatedData(Request $request, DataTable $dataTable, bool $fillDate = false): array
    {
        $request->validate(['values' => ['present', 'array']]);

        return self::parseValues($dataTable, $request->input('values', []), $fillDate);
    }

    /**
     * A row's data from what was typed, by the table's columns; a validation
     * error per column (values.{key}) otherwise.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $errorKeys  column key → the key its error is reported under
     */
    public static function parseValues(DataTable $dataTable, array $values, bool $fillDate = false, array $errorKeys = []): array
    {
        $date = $dataTable->dateColumn();

        if ($fillDate && $date && trim((string) ($values[$date->key] ?? '')) === '') {
            $values[$date->key] = now(app(TenantContext::class)->current()->timezone ?: config('app.timezone'))
                ->format($date->type === DataColumnType::DateTime ? 'Y-m-d H:i' : 'Y-m-d');
        }

        [$data, $errors] = DataTableSchema::parseRow($dataTable, $values);

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn (string $error, string $key) => ['values.'.($errorKeys[$key] ?? $key) => [$error]])->all(),
            );
        }

        return $data;
    }
}
