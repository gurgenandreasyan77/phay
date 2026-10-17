<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\Import\ColumnMatcher;
use App\DataTables\Import\DataImporter;
use App\DataTables\Import\FileReader;
use App\DataTables\Import\Sheet;
use App\DataTables\Import\UnreadableFile;
use App\Http\Controllers\Controller;
use App\Models\DataImport;
use App\Models\DataTable;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Import of a CSV, XLSX or XML file into a business's own table, in two
 * steps: upload (the file is read and a column mapping is suggested), then
 * run with the mapping the person confirmed. A run can be taken back.
 */
class DataImportController extends Controller
{
    private const DISK = 'local';

    public function __construct(private readonly FileReader $reader) {}

    /** The imports of a table, newest first. */
    public function index(DataTable $dataTable): JsonResponse
    {
        DataTableController::ensureReachable($dataTable);

        $imports = DataImport::query()
            ->where('data_table_id', $dataTable->id)
            ->with('user')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json(['data' => $imports->map(fn (DataImport $import) => $this->summary($import))]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'data_table_id' => ['nullable', 'integer'],
        ]);

        $table = $this->table($request->input('data_table_id'));
        $file = $request->file('file');
        $format = FileReader::format($file->getClientOriginalName());

        if (! $format) {
            throw ValidationException::withMessages(['file' => ['Ընտրեք CSV, XLSX կամ XML ֆայլ։']]);
        }

        $path = $file->storeAs(
            'imports/'.app(TenantContext::class)->id(),
            Str::uuid()->toString().'.'.($format === 'csv' ? 'csv' : $format),
            self::DISK,
        );

        try {
            $sheet = $this->reader->read(Storage::disk(self::DISK)->path($path), $format);
        } catch (UnreadableFile $e) {
            Storage::disk(self::DISK)->delete($path);

            throw ValidationException::withMessages(['file' => [$e->getMessage()]]);
        }

        $import = DataImport::create([
            'data_table_id' => $table?->id,
            'user_id' => $request->user()->id,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'format' => $format,
            'path' => $path,
            'headers' => $sheet->headers,
            'total_rows' => count($sheet->rows),
        ]);

        return response()->json(['data' => $this->preview($import, $sheet, $table)], 201);
    }

    /** The upload again, mapped to a table (e.g. one just made from this file). */
    public function show(Request $request, DataImport $dataImport): JsonResponse
    {
        $table = $this->table($request->input('data_table_id') ?? $dataImport->data_table_id);

        if ($dataImport->status !== DataImport::STATUS_UPLOADED) {
            return response()->json(['data' => $this->summary($dataImport)]);
        }

        return response()->json(['data' => $this->preview($dataImport, $this->sheet($dataImport), $table)]);
    }

    public function run(Request $request, DataImport $dataImport, DataImporter $importer): JsonResponse
    {
        $validated = $request->validate([
            'data_table_id' => ['required', 'integer'],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_unless($dataImport->status === DataImport::STATUS_UPLOADED, 409, 'Այս ֆայլն արդեն ներմուծված է։');

        $table = $this->table($validated['data_table_id']);
        $width = count($dataImport->headers);

        // Only the table's own columns, each pointing at a real file column.
        $mapping = collect($table->columnDefinitions())
            ->mapWithKeys(function ($column) use ($validated, $width) {
                $index = $validated['mapping'][$column->key] ?? null;

                return [$column->key => $index !== null && $index < $width ? (int) $index : null];
            })
            ->all();

        if (array_filter($mapping, fn ($index) => $index !== null) === []) {
            throw ValidationException::withMessages(['mapping' => ['Ընտրեք առնվազն մեկ սյունակ ներմուծելու համար։']]);
        }

        $import = $importer->run($dataImport, $table, $this->sheet($dataImport), $mapping);

        Storage::disk(self::DISK)->delete($import->path);
        $import->update(['path' => null]);

        return response()->json(['data' => $this->summary($import->fresh('user'))]);
    }

    /** Takes an import back: the rows it added are deleted. */
    public function undo(DataImport $dataImport): JsonResponse
    {
        abort_unless($dataImport->status === DataImport::STATUS_DONE, 409, 'Այս ներմուծումը հնարավոր չէ հետ վերցնել։');
        if ($dataImport->table) {
            DataTableController::ensureReachable($dataImport->table);
        }

        $dataImport->rows()->delete();
        $dataImport->update(['status' => DataImport::STATUS_UNDONE]);

        return response()->json(['data' => $this->summary($dataImport->fresh('user'))]);
    }

    private function table(mixed $id): ?DataTable
    {
        if ($id === null || $id === '') {
            return null;
        }

        $table = DataTable::query()->find((int) $id);
        abort_unless($table, 404);
        DataTableController::ensureReachable($table);

        return $table;
    }

    private function sheet(DataImport $import): Sheet
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($import->path && $disk->exists($import->path), 410, 'Ֆայլն այլևս հասանելի չէ։ Վերբեռնեք այն նորից։');

        return $this->reader->read($disk->path($import->path), $import->format);
    }

    private function preview(DataImport $import, Sheet $sheet, ?DataTable $table): array
    {
        return [
            ...$this->summary($import),
            'sampleRows' => array_slice($sheet->rows, 0, 5),
            'truncated' => $sheet->truncated,
            'maxRows' => FileReader::MAX_ROWS,
            'mapping' => $table ? ColumnMatcher::suggest($table->columnDefinitions(), $sheet->headers) : null,
            'guessedColumns' => ColumnMatcher::guessColumns($sheet->headers, $sheet->rows),
        ];
    }

    private function summary(DataImport $import): array
    {
        return [
            'id' => $import->id,
            'tableId' => $import->data_table_id,
            'fileName' => $import->file_name,
            'format' => $import->format,
            'headers' => $import->headers,
            'totalRows' => $import->total_rows,
            'status' => $import->status,
            'importedRows' => $import->imported_rows,
            'skippedRows' => $import->skipped_rows,
            'errors' => $import->errors ?? [],
            'user' => $import->user?->name,
            'createdAt' => $import->created_at?->toIso8601String(),
        ];
    }
}
