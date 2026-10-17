<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The upholstery calculator and its add-on services became an optional
 * module ("dry-cleaning", off by default). Servio, the default business,
 * keeps it: its site uses both.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id');

        if (! $tenantId) {
            return;
        }

        $row = ['name' => 'dry-cleaning', 'scope' => 'tenant:'.$tenantId];

        if (! DB::table('features')->where($row)->exists()) {
            DB::table('features')->insert($row + ['value' => 'true', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('features')->where('name', 'dry-cleaning')->delete();
    }
};
