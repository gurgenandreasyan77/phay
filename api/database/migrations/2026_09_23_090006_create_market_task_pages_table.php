<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_task_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug_hy')->unique();
            $table->string('slug_ru')->unique();
            $table->string('slug_en')->unique();
            $table->string('status')->default('draft');
            $table->json('title');
            $table->json('intro')->nullable();
            $table->json('selection_rules')->nullable();
            $table->json('precautions')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->foreignId('related_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_task_pages');
    }
};
