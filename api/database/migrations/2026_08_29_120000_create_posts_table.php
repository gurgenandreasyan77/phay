<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category');
            $table->json('tags')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('author_name')->default('Phay');
            $table->string('featured_image_path')->nullable();
            $table->json('featured_image_alt')->nullable();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('content');
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('related_post_slugs')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
