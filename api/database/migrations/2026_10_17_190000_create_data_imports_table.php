<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One uploaded file (CSV, XLSX, XML) and what came of it: kept so the
        // admin shows the import history and can take an import back.
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_table_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('format', 10);
            $table->string('path')->nullable();
            $table->jsonb('headers');
            $table->unsignedInteger('total_rows')->default(0);
            $table->string('status', 20)->default('uploaded');
            $table->jsonb('mapping')->nullable();
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->jsonb('errors')->nullable();
            $table->timestamps();

            $table->index(['data_table_id', 'id']);
        });

        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->foreignId('data_import_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_import_id');
        });

        Schema::dropIfExists('data_imports');
    }
};
