<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['product_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
    }
};
