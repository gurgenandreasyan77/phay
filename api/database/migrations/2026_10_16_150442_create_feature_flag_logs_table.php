<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->boolean('old_value')->nullable();
            $table->boolean('new_value');
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_logs');
    }
};
