<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\DataTableSchema;
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

        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) $request->input('search'));

        $rows = $dataTable->rows()
            ->when($search !== '', fn ($query) => $query->whereRaw(
                'LOWER(CAST(data AS TEXT)) LIKE ?',
                ['%'.mb_strtolower(addcslashes($search, '%_\\')).'%'],
            ))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return DataTableRowResource::collection($rows);
    }

    public function store(Request $request, DataTable $dataTable): JsonResponse
    {
        DataTableController::ensureReachable($dataTable);

        $row = $dataTable->rows()->create([
            'data' => $this->validatedData($request, $dataTable),
            'source' => DataTableRow::SOURCE_MANUAL,
            'created_by_id' => $request->user()->id,
        ]);

        return (new DataTableRowResource($row))->response()->setStatusCode(201);
    }

    public function update(Request $request, DataTable $dataTable, DataTableRow $row): DataTableRowResource
    {
        DataTableController::ensureReachable($dataTable);

        $row->update(['data' => $this->validatedData($request, $dataTable)]);

        return new DataTableRowResource($row);
    }

    public function destroy(DataTable $dataTable, DataTableRow $row): Response
    {
        DataTableController::ensureReachable($dataTable);

        $row->delete();

        return response()->noContent();
    }

    private function validatedData(Request $request, DataTable $dataTable): array
    {
        $request->validate(['values' => ['present', 'array']]);

        [$data, $errors] = DataTableSchema::parseRow($dataTable, $request->input('values', []));

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn (string $error, string $key) => ["values.{$key}" => [$error]])->all(),
            );
        }

        return $data;
    }
}
