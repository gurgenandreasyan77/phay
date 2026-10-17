<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('service_id')->constrained('service_packages')->nullOnDelete();
            $table->string('car_brand')->nullable()->after('property_type');
            $table->string('car_model')->nullable()->after('car_brand');
            $table->unsignedSmallInteger('car_year')->nullable()->after('car_model');
            $table->string('car_body_type')->nullable()->after('car_year');
            $table->unsignedTinyInteger('seats_count')->nullable()->after('car_body_type');
            $table->string('interior_material')->nullable()->after('seats_count');
            $table->string('soiling_level')->nullable()->after('interior_material');
            $table->json('contamination_types')->nullable()->after('soiling_level');
            $table->boolean('has_odor')->nullable()->after('contamination_types');
            $table->string('odor_source')->nullable()->after('has_odor');
            $table->boolean('has_pet_hair')->nullable()->after('odor_source');
            $table->boolean('has_child_seat')->nullable()->after('has_pet_hair');
            $table->boolean('can_bring_car')->nullable()->after('has_child_seat');
            $table->string('preferred_channel')->nullable()->after('can_bring_car');
            $table->json('photos')->nullable()->after('preferred_channel');
            $table->text('admin_comment')->nullable()->after('comment');
            $table->unsignedInteger('estimated_price_min')->nullable()->after('admin_comment');
            $table->unsignedInteger('estimated_price_max')->nullable()->after('estimated_price_min');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn([
                'car_brand',
                'car_model',
                'car_year',
                'car_body_type',
                'seats_count',
                'interior_material',
                'soiling_level',
                'contamination_types',
                'has_odor',
                'odor_source',
                'has_pet_hair',
                'has_child_seat',
                'can_bring_car',
                'preferred_channel',
                'photos',
                'admin_comment',
                'estimated_price_min',
                'estimated_price_max',
            ]);
        });
    }
};
