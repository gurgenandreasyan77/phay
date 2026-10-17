<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->json('name');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->json('included_items')->nullable();
            $table->string('price_type')->nullable();
            $table->unsignedInteger('price_amount')->nullable();
            $table->unsignedInteger('price_min')->nullable();
            $table->unsignedInteger('price_max')->nullable();
            $table->json('price_by_car_type')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
