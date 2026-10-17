<?php

namespace App\DataTables;

use App\Enums\DataColumnType;
use App\Models\DataTable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Columns of a business's own table and the rows that follow them.
 */
final class DataTableSchema
{
    public const MAX_COLUMNS = 50;

    /**
     * Column definitions as the admin sends them, with stable keys: a column
     * that already exists keeps its key (so renaming it keeps the data),
     * a new one gets a fresh key never used in this table before.
     *
     * @param  list<array>  $columns  validated input
     * @return list<array>
     */
    public static function columns(array $columns, ?DataTable $existing = null): array
    {
        $known = collect($existing?->columns ?? [])->pluck('key')->all();
        $result = [];
        $labels = [];

        foreach ($columns as $index => $column) {
            $label = trim($column['label']);
            $lower = mb_strtolower($label);

            if (in_array($lower, $labels, true)) {
                throw ValidationException::withMessages(["columns.{$index}.label" => ['Այս անունով սյունակ արդեն կա։']]);
            }
            $labels[] = $lower;

            $type = DataColumnType::from($column['type']);
            $options = array_values(array_unique(array_filter(array_map('trim', $column['options'] ?? []), 'strlen')));

            if ($type === DataColumnType::Select && $options === []) {
                throw ValidationException::withMessages(["columns.{$index}.options" => ['Ավելացրեք առնվազն մեկ տարբերակ։']]);
            }

            $key = isset($column['key']) && in_array($column['key'], $known, true) ? $column['key'] : self::newKey();

            $result[] = (new DataColumn($key, $label, $type, (bool) ($column['required'] ?? false), $options))->toArray();
        }

        return $result;
    }

    /** Validation rules for the `columns` field of a table request. */
    public static function rules(): array
    {
        return [
            'columns' => ['required', 'array', 'min:1', 'max:'.self::MAX_COLUMNS],
            'columns.*.key' => ['nullable', 'string', 'max:20'],
            'columns.*.label' => ['required', 'string', 'max:100'],
            'columns.*.type' => ['required', 'string', 'in:'.implode(',', DataColumnType::values())],
            'columns.*.required' => ['sometimes', 'boolean'],
            'columns.*.options' => ['sometimes', 'array', 'max:100'],
            'columns.*.options.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * A row's values, keyed by column key, parsed by column type. Values of
     * keys that aren't columns of the table are dropped. The table's date
     * column is always required: a row without a date can't be counted in
     * any period.
     *
     * @param  array<string, string>  $dateOrders  column key → Moment::DAY_FIRST / MONTH_FIRST
     * @return array{0: array<string, mixed>, 1: array<string, string>} the data and the errors per column key
     */
    public static function parseRow(DataTable $table, array $input, array $dateOrders = []): array
    {
        $data = [];
        $errors = [];
        $dateKey = $table->dateColumn()?->key;

        foreach ($table->columnDefinitions() as $column) {
            [$value, $error] = $column->parse($input[$column->key] ?? null, $dateOrders[$column->key] ?? null);

            if ($error === null && $value === null && $column->key === $dateKey) {
                $error = 'Պարտադիր դաշտ է։';
            }

            if ($error !== null) {
                $errors[$column->key] = $error;
            }

            $data[$column->key] = $value;
        }

        return [$data, $errors];
    }

    /** Validation rules for the date, amount and quantity column choices. */
    public static function metricRules(): array
    {
        return [
            'date_column' => ['sometimes', 'nullable', 'string', 'max:20'],
            'amount_column' => ['sometimes', 'nullable', 'string', 'max:20'],
            'quantity_column' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Applies the choices sent with a table: a column may be named by its key
     * or, for a column that is new in this request, by its label.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function applyMetrics(DataTable $table, array $validated): void
    {
        $explicit = [];

        foreach (['date_column', 'amount_column', 'quantity_column'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $explicit[] = $field;
            $wanted = $validated[$field];
            $table->{$field} = $wanted === null || $wanted === ''
                ? null
                : collect($table->columnDefinitions())->first(fn (DataColumn $c) => $c->key === $wanted || mb_strtolower($c->label) === mb_strtolower(trim($wanted)))?->key;
        }

        $table->fillMetricColumns($explicit);
    }

    private static function newKey(): string
    {
        return 'c_'.Str::lower(Str::random(8));
    }
}
