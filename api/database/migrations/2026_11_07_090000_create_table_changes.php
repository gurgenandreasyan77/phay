<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changes an AI agent prepared in the business's tables (App\DataTables\Changes\TableChanges):
 * a new table, its name and columns, rows added, edited or deleted, tables
 * deleted. The agent only plans them (status planned); the owner applies
 * one from its card in the chat, and may undo it while nobody touched what
 * it changed.
 *
 * Also the new agent that does this work, Sona (key "tables"): its price,
 * and every business that had the accountant (who split and joined tables
 * until now) or never pays gets her.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // create_table, edit_table, delete_tables, add_rows, update_rows, delete_rows.
            $table->string('kind', 20);
            // orders, sales or expenses (App\Enums\DataTableSection).
            $table->string('section', 16);
            // The table it changes (null: a new table, or several deleted).
            $table->foreignId('data_table_id')->nullable()->constrained('data_tables')->nullOnDelete();
            // What exactly changes: the table before and after, the rows with their old and new values.
            $table->json('payload');
            // What it made: the new table's id, the new rows' ids.
            $table->json('result')->nullable();
            // planned, applied, undone or discarded.
            $table->string('status', 16);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        $now = now();
        DB::table('module_prices')->insertOrIgnore([
            'feature_key' => 'agent:tables',
            'price' => 2900,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tenants = DB::table('tenant_agents')->where('agent_key', 'accountant')->where('active', true)->pluck('tenant_id')
            ->merge(DB::table('subscriptions')->where('unlimited', true)->pluck('tenant_id'))
            ->unique();
        foreach ($tenants as $tenantId) {
            DB::table('tenant_agents')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'agent_key' => 'tables',
                'active' => true,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('table_changes');
        DB::table('tenant_agents')->where('agent_key', 'tables')->delete();
        DB::table('module_prices')->where('feature_key', 'agent:tables')->delete();
    }
};
