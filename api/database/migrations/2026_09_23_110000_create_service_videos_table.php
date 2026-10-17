<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->json('title')->nullable();
            $table->string('video_path');
            $table->string('poster_image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_videos');
    }
};
