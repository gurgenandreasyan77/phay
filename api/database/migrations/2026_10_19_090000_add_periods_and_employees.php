<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a table is kept for (App\Enums\TablePeriod) and the first day
        // of its period. Null: not chosen yet, the month is read from the
        // name or rows as before (see PeriodTables).
        Schema::table('data_tables', function (Blueprint $table) {
            $table->string('period', 10)->nullable()->after('columns');
            $table->date('period_start')->nullable()->after('period');

            $table->index(['tenant_id', 'section', 'period', 'period_start']);
        });

        // The people of a business who do its orders.
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('position', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });

        // Who did a row, read from its performer column when it's saved (by
        // the employee's name), like occurred_at and amount.
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('amount')->constrained()->nullOnDelete();

            $table->index(['employee_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'occurred_at']);
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::dropIfExists('employees');

        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'section', 'period', 'period_start']);
            $table->dropColumn(['period', 'period_start']);
        });
    }
};
