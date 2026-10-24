<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each site gets its own cache-refresh webhook, so the platform no longer
     * assumes one site. The default tenant's site takes over the address and
     * secret the admin app used to have in its env, so nothing changes for it.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('revalidate_url')->nullable()->after('allowed_origins');
            $table->text('revalidate_secret')->nullable()->after('revalidate_url');
        });

        $url = config('platform.legacy_site.revalidate_url');
        $secret = config('platform.legacy_site.revalidate_secret');
        $tenantId = DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id');

        if ($url && $secret && $tenantId) {
            DB::table('sites')->where('tenant_id', $tenantId)->whereNull('revalidate_url')->update([
                'revalidate_url' => $url,
                'revalidate_secret' => encrypt($secret, false),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['revalidate_url', 'revalidate_secret']);
        });
    }
};
