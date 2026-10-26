<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables split into periods or put together into a bigger one (a year's
 * table into its months, twelve months into a year): which tables went in,
 * which came out, and whether it was applied or undone. The tables that
 * went in are archived (archived_at) with the reshape that replaced them.
 *
 * An AI agent only plans a reshape (status planned): the owner applies it
 * from the card in the chat. Answers of the assistant keep such cards and
 * download links in assistant_messages.actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_reshapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // orders, sales or expenses (App\Enums\DataTableSection).
            $table->string('section', 16);
            // The period of the new tables (App\Enums\TablePeriod; custom: one table).
            $table->string('period', 16);
            // Rows without a date: separate (a table of their own) or latest (the newest table).
            $table->string('undated', 16);
            $table->json('source_ids');
            $table->json('result_ids')->nullable();
            // planned, applied or undone.
            $table->string('status', 16);
            // What it made: tables, rows per table (to tell when they were changed since).
            $table->json('summary')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::table('data_tables', function (Blueprint $table) {
            // The reshape that replaced (archived) this table.
            $table->foreignId('reshape_id')->nullable()->after('replaced_by_id')->constrained('table_reshapes')->nullOnDelete();
        });

        Schema::table('assistant_messages', function (Blueprint $table) {
            // Cards an answer offers: a reshape to apply, a file to download.
            $table->json('actions')->nullable()->after('refused');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropColumn('actions');
        });

        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reshape_id');
        });

        Schema::dropIfExists('table_reshapes');
    }
};
