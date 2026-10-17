<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_settings', function (Blueprint $table) {
            $table->id();
            // { apartment: { regular, general, postConstruction }, house: {...}, office: {...} }
            $table->json('cleaning_rates');
            $table->unsignedInteger('bathroom_rate');
            $table->unsignedInteger('vitrage_rate');
            $table->unsignedInteger('extra_floor_rate');
            $table->decimal('heavy_soiling_multiplier', 4, 2);
            $table->unsignedInteger('cleaning_minimum');
            // { sofa2, sofa3, sofaCorner, armchair, chair, mattressSingle, mattressDouble, carpet, steam }
            $table->json('dry_cleaning_rates');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculator_settings');
    }
};
