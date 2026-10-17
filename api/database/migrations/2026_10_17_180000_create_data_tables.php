<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A business's own tables (orders, sales...) with the columns it
        // chose: the definition lives in `columns`, each row's values in
        // data_table_rows.data, keyed by the column's stable key. No SQL
        // table per business, so nothing needs a migration when a business
        // adds a column.
        Schema::create('data_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('columns');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'section']);
        });

        Schema::create('data_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_table_id')->constrained()->cascadeOnDelete();
            $table->jsonb('data');
            $table->string('source', 20)->default('manual');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['data_table_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_table_rows');
        Schema::dropIfExists('data_tables');
    }
};
