<?php

use App\DataTables\RowMetrics;
use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Models\DataTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A column's meaning moves into the column itself (`role` in `columns`):
     * the date, amount and quantity choices become roles (the amount of a
     * table with a quantity was a price), other columns get the role their
     * name suggests, and every row's date and amount is read again.
     */
    public function up(): void
    {
        DataTable::withoutGlobalScopes()->each(function (DataTable $table) {
            $chosen = array_filter([
                $table->getAttribute('date_column') => ColumnRole::Date,
                $table->getAttribute('quantity_column') => ColumnRole::Quantity,
                $table->getAttribute('amount_column') => $table->getAttribute('quantity_column') ? ColumnRole::UnitPrice : ColumnRole::Amount,
            ], fn ($role, $key) => $key !== '', ARRAY_FILTER_USE_BOTH);

            $columns = $table->columns ?? [];
            $used = [];
            foreach ($columns as $i => $column) {
                $role = $chosen[$column['key']] ?? null;
                $columns[$i]['role'] = $role?->value;
                if ($role) {
                    $used[$role->value] = true;
                }
            }
            foreach ($columns as $i => $column) {
                $role = $column['role'] ? null : ColumnRole::guess($column['label']);
                if ($role && ! isset($used[$role->value]) && in_array(DataColumnType::from($column['type']), $role->types(), true)) {
                    $columns[$i]['role'] = $role->value;
                    $used[$role->value] = true;
                }
            }

            $table->columns = $columns;
            $table->saveQuietly();
            RowMetrics::recompute($table);
        });

        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropColumn(['date_column', 'amount_column', 'quantity_column']);
        });
    }

    public function down(): void
    {
        Schema::table('data_tables', function (Blueprint $table) {
            $table->string('date_column', 20)->nullable()->after('columns');
            $table->string('amount_column', 20)->nullable()->after('date_column');
            $table->string('quantity_column', 20)->nullable()->after('amount_column');
        });

        DataTable::withoutGlobalScopes()->each(function (DataTable $table) {
            $key = fn (ColumnRole $role) => $table->roleColumn($role)?->key;
            $table->forceFill([
                'date_column' => $key(ColumnRole::Date),
                'amount_column' => $key(ColumnRole::Amount) ?? $key(ColumnRole::UnitPrice),
                'quantity_column' => $key(ColumnRole::Quantity),
            ])->saveQuietly();
        });
    }
};
