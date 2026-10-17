<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // What the client actually paid — revenue in analytics comes from
            // completed orders' final price, not from the estimate.
            $table->unsignedInteger('final_price')->nullable()->after('estimated_price_max');
            $table->timestamp('completed_at')->nullable()->after('final_price');
            $table->index(['tenant_id', 'created_at']);
        });

        // Orders completed before this column existed count from their last update.
        DB::table('leads')->where('status', 'completed')->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn(['final_price', 'completed_at']);
        });
    }
};
