<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('car_type')->nullable();
            $table->json('label')->nullable();
            $table->json('description')->nullable();
            $table->string('before_image');
            $table->string('after_image');
            $table->json('alt_before')->nullable();
            $table->json('alt_after')->nullable();
            $table->date('performed_at')->nullable();
            // Both must be true for the item to ever be shown publicly — see
            // ServiceGalleryItemController@index — consent is a legal
            // precondition, is_published is the editorial go-ahead.
            $table->boolean('client_consent')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_gallery_items');
    }
};
