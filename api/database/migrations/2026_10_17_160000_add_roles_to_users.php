<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform staff who onboard clients; is_super_admin still means
            // full platform rights.
            $table->boolean('is_platform_moderator')->default(false)->after('is_super_admin');
        });

        // Business roles are now owner | admin | moderator.
        DB::table('tenant_user')->where('role', 'editor')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        DB::table('tenant_user')->whereIn('role', ['admin', 'moderator'])->update(['role' => 'editor']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_moderator');
        });
    }
};
