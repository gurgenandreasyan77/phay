<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Lifecycle
            $table->string('status')->default('draft')->after('category');

            // Per-locale slugs (nullable until backfilled below, then used going forward)
            $table->string('slug_hy')->nullable()->unique()->after('slug');
            $table->string('slug_ru')->nullable()->unique()->after('slug_hy');
            $table->string('slug_en')->nullable()->unique()->after('slug_ru');

            // Catalog identity
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('sku')->nullable();
            $table->string('gtin')->nullable();
            $table->decimal('volume_value', 8, 2)->nullable();
            $table->string('volume_unit')->nullable();
            $table->unsignedInteger('old_price_amount')->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->string('availability_status')->nullable();
            $table->text('admin_note')->nullable();

            // Purpose & usage
            $table->json('short_description')->nullable();
            $table->json('stains')->nullable();
            $table->json('surfaces')->nullable();
            $table->json('forbidden_surfaces')->nullable();
            $table->decimal('ph_value', 3, 1)->nullable();
            $table->string('composition_type')->nullable();
            $table->json('dilution_table')->nullable();
            $table->json('usage_steps')->nullable();
            $table->boolean('requires_extractor')->nullable();
            $table->json('consumption_note')->nullable();
            $table->json('contact_time')->nullable();
            $table->json('rinse_method')->nullable();
            $table->json('safety_notes')->nullable();
            $table->json('storage_conditions')->nullable();
            $table->json('shelf_life')->nullable();
            $table->boolean('batch_tracked')->default(false);
            $table->string('sds_url')->nullable();
            $table->json('certificate_note')->nullable();

            // SEO
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('og_title')->nullable();
            $table->json('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_override')->nullable();
            $table->string('seo_robots')->default('index');
            $table->string('primary_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
        });

        // Backfill existing rows so they keep working under the new schema:
        // per-locale slugs default to the single legacy slug, and every row
        // is forced to `draft` regardless of its old `is_active` value, since
        // none of the 30 seeded demo rows have the verified brand/pH/safety/
        // real-photo data the spec requires before something can be published.
        DB::table('products')->update([
            'slug_hy' => DB::raw('slug'),
            'slug_ru' => DB::raw('slug'),
            'slug_en' => DB::raw('slug'),
            'status' => 'draft',
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'slug_hy',
                'slug_ru',
                'slug_en',
                'brand',
                'manufacturer',
                'country_of_origin',
                'sku',
                'gtin',
                'volume_value',
                'volume_unit',
                'old_price_amount',
                'stock_quantity',
                'availability_status',
                'admin_note',
                'short_description',
                'stains',
                'surfaces',
                'forbidden_surfaces',
                'ph_value',
                'composition_type',
                'dilution_table',
                'usage_steps',
                'requires_extractor',
                'consumption_note',
                'contact_time',
                'rinse_method',
                'safety_notes',
                'storage_conditions',
                'shelf_life',
                'batch_tracked',
                'sds_url',
                'certificate_note',
                'seo_title',
                'seo_description',
                'og_title',
                'og_description',
                'og_image',
                'canonical_override',
                'seo_robots',
                'primary_keyword',
                'secondary_keywords',
            ]);
        });
    }
};
