<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('old_slug');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['locale', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_slug_redirects');
    }
};
