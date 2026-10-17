<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table whose rows belong to one business, with the unique
     * indexes that become unique per business instead of globally (two
     * businesses may both have a service with the slug "sofa").
     *
     * Pivot tables are left out: they only link rows of the same business.
     */
    private const TABLES = [
        'service_categories' => [['slug']],
        'services' => [['slug']],
        'leads' => [],
        'posts' => [['slug']],
        'site_settings' => [],
        'contact_channels' => [],
        'additional_services' => [['slug']],
        'calculator_settings' => [],
        'products' => [['slug'], ['slug_hy'], ['slug_ru'], ['slug_en']],
        'product_categories' => [['slug_hy'], ['slug_ru'], ['slug_en']],
        'product_images' => [],
        'product_slug_redirects' => [['locale', 'old_slug']],
        'market_task_pages' => [['slug_hy'], ['slug_ru'], ['slug_en']],
        'service_packages' => [['slug']],
        'service_gallery_items' => [],
        'service_slug_redirects' => [['old_slug']],
        'service_videos' => [],
        'feature_flag_logs' => [],
    ];

    // One row per business rather than one row per app.
    private const SINGLETONS = ['site_settings', 'calculator_settings'];

    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id');

        foreach (self::TABLES as $table => $uniques) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained();
            });

            DB::table($table)->update(['tenant_id' => $tenantId]);

            Schema::table($table, function (Blueprint $blueprint) use ($table, $uniques) {
                $blueprint->unsignedBigInteger('tenant_id')->nullable(false)->change();

                foreach ($uniques as $columns) {
                    $blueprint->dropUnique($columns);
                    $blueprint->unique(['tenant_id', ...$columns]);
                }

                if (in_array($table, self::SINGLETONS, true)) {
                    $blueprint->unique('tenant_id');
                } elseif ($uniques === []) {
                    $blueprint->index('tenant_id');
                }
            });
        }

        // Feature flags were global (Feature::globally() stores them under
        // the "__laravel_global" scope); they become the default business's
        // flags, keeping whatever was switched on or off.
        DB::table('features')->where('scope', '__laravel_global')->orderBy('id')->each(function ($feature) use ($tenantId) {
            DB::table('features')->updateOrInsert(
                ['name' => $feature->name, 'scope' => 'tenant:'.$tenantId],
                ['value' => $feature->value, 'created_at' => now(), 'updated_at' => now()],
            );
        });
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $table => $uniques) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $uniques) {
                foreach ($uniques as $columns) {
                    $blueprint->dropUnique(['tenant_id', ...$columns]);
                    $blueprint->unique($columns);
                }

                if (in_array($table, self::SINGLETONS, true)) {
                    $blueprint->dropUnique(['tenant_id']);
                } elseif ($uniques === []) {
                    $blueprint->dropIndex(['tenant_id']);
                }

                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }

        DB::table('features')->where('scope', 'like', 'tenant:%')->delete();
    }
};
