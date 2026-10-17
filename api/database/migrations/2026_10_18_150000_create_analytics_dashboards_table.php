<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the owner chose to see on the analytics screen, per section
        // (orders, sales): a list of DashboardWidget values, in their order.
        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->jsonb('widgets');
            $table->timestamps();

            $table->unique(['tenant_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_dashboards');
    }
};
