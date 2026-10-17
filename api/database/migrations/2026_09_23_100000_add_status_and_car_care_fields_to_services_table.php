<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->json('car_types')->nullable()->after('status');
            $table->json('materials')->nullable()->after('car_types');
            $table->json('contamination_types')->nullable()->after('materials');
            $table->string('price_type')->nullable()->after('contamination_types');
            $table->unsignedInteger('price_amount')->nullable()->after('price_type');
            $table->unsignedInteger('price_min')->nullable()->after('price_amount');
            $table->unsignedInteger('price_max')->nullable()->after('price_min');
            $table->json('price_by_car_type')->nullable()->after('price_max');
            $table->unsignedInteger('duration_minutes')->nullable()->after('price_by_car_type');
            $table->json('drying_time_note')->nullable()->after('duration_minutes');
            $table->json('warnings')->nullable()->after('drying_time_note');
            $table->json('og_title')->nullable()->after('seo_description');
            $table->json('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('canonical_override')->nullable()->after('og_image');
            $table->string('seo_robots')->nullable()->after('canonical_override');
        });

        DB::table('services')->where('is_active', true)->update(['status' => 'active']);
        DB::table('services')->where('is_active', false)->update(['status' => 'archived']);

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        DB::table('services')->where('status', 'active')->update(['is_active' => true]);
        DB::table('services')->where('status', '!=', 'active')->update(['is_active' => false]);

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'car_types',
                'materials',
                'contamination_types',
                'price_type',
                'price_amount',
                'price_min',
                'price_max',
                'price_by_car_type',
                'duration_minutes',
                'drying_time_note',
                'warnings',
                'og_title',
                'og_description',
                'og_image',
                'canonical_override',
                'seo_robots',
            ]);
        });
    }
};
