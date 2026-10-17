<?php

namespace App\DataTables\Import;

use App\DataTables\DataTableSchema;
use App\Models\DataImport;
use App\Models\DataTable;
use App\Models\DataTableRow;
use Illuminate\Support\Facades\DB;

/**
 * Puts the rows of an uploaded file into a table: each file column goes to
 * the table column it's mapped to, values are parsed by column type, and a
 * row with an invalid value is skipped and reported (not half-imported).
 */
final class DataImporter
{
    /** Skipped rows kept in the report, with their reasons. */
    public const MAX_REPORTED_ERRORS = 200;

    private const CHUNK = 500;

    /**
     * @param  array<string, ?int>  $mapping  table column key → file column index
     */
    public function run(DataImport $import, DataTable $table, Sheet $sheet, array $mapping): DataImport
    {
        $labels = collect($table->columnDefinitions())->mapWithKeys(fn ($c) => [$c->key => $c->label]);
        $errors = [];
        $skipped = 0;
        $imported = 0;
        $now = now();

        DB::transaction(function () use ($import, $table, $sheet, $mapping, $labels, $now, &$errors, &$skipped, &$imported) {
            $batch = [];

            foreach ($sheet->rows as $index => $cells) {
                $input = [];
                foreach ($mapping as $key => $column) {
                    $input[$key] = $column === null ? null : ($cells[$column] ?? null);
                }

                [$data, $rowErrors] = DataTableSchema::parseRow($table, $input);

                if ($rowErrors !== []) {
                    $skipped++;
                    if (count($errors) < self::MAX_REPORTED_ERRORS) {
                        $errors[] = [
                            // The file's own line: header is line 1 (empty lines aside).
                            'line' => $index + 2,
                            'errors' => collect($rowErrors)
                                ->mapWithKeys(fn (string $message, string $key) => [$labels[$key] ?? $key => $message])
                                ->all(),
                        ];
                    }

                    continue;
                }

                $batch[] = [
                    'tenant_id' => $table->tenant_id,
                    'data_table_id' => $table->id,
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'source' => DataTableRow::SOURCE_IMPORT,
                    'data_import_id' => $import->id,
                    'created_by_id' => $import->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $imported++;

                if (count($batch) === self::CHUNK) {
                    DataTableRow::insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DataTableRow::insert($batch);
            }
        });

        $import->update([
            'data_table_id' => $table->id,
            'status' => DataImport::STATUS_DONE,
            'mapping' => $mapping,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'errors' => $errors,
        ]);

        return $import;
    }
}
