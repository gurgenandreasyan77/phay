<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same order or sale written in several tables (a copy of a table, a
 * table made again from Google, a file imported twice) is counted once
 * (App\DataTables\Duplicates\RowDuplicates):
 *
 * - data_table_rows.duplicate_of_id: the row this one is an exact copy of;
 *   such a row is left out of the calendar, analytics, finance and the AI
 *   (DataTableRow::scopeCounted). Kept up to date after every change.
 * - row_duplicate_dismissals: two rows the owner said are different
 *   records («keep both»): never counted once, never asked again.
 *
 * Filled by `php artisan tables:duplicates` (run after migrating).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->foreignId('duplicate_of_id')->nullable()->constrained('data_table_rows')->nullOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('row_duplicate_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // The smaller row id first.
            $table->foreignId('row_id')->constrained('data_table_rows')->cascadeOnDelete();
            $table->foreignId('other_row_id')->constrained('data_table_rows')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['row_id', 'other_row_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('row_duplicate_dismissals');

        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'occurred_at']);
            $table->dropConstrainedForeignId('duplicate_of_id');
        });
    }
};
