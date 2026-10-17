<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_task_page_product', function (Blueprint $table) {
            $table->foreignId('market_task_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['market_task_page_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_task_page_product');
    }
};
