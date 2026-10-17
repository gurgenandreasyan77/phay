<?php

use App\DataTables\RowMetrics;
use App\Models\DataTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which columns of a table say when a row happened and what it is
        // worth (keys of its `columns`), chosen by the business.
        Schema::table('data_tables', function (Blueprint $table) {
            $table->string('date_column', 20)->nullable()->after('columns');
            $table->string('amount_column', 20)->nullable()->after('date_column');
            $table->string('quantity_column', 20)->nullable()->after('amount_column');
        });

        // The same two facts of each row, read from its data when it's saved:
        // analytics and the AI assistant sum and group these instead of
        // parsing every row's JSON. occurred_at is the business's local time.
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->after('data');
            $table->decimal('amount', 14, 2)->nullable()->after('occurred_at');

            $table->index(['data_table_id', 'occurred_at']);
        });

        DataTable::withoutTenancy()->each(function (DataTable $table) {
            $table->fillMetricColumns();
            $table->saveQuietly();
            RowMetrics::recompute($table);
        });
    }

    public function down(): void
    {
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropIndex(['data_table_id', 'occurred_at']);
            $table->dropColumn(['occurred_at', 'amount']);
        });

        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropColumn(['date_column', 'amount_column', 'quantity_column']);
        });
    }
};
