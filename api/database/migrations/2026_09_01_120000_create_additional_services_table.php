<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category');
            $table->json('name');
            $table->json('note')->nullable();
            $table->json('alt');
            $table->string('image');
            $table->unsignedInteger('price_amount');
            $table->string('price_unit')->default('flat');
            $table->string('service_slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_services');
    }
};
