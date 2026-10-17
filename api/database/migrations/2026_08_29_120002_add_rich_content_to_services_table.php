<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('who_its_for')->nullable()->after('description');
            $table->json('included_items')->nullable()->after('who_its_for');
            $table->json('excluded_items')->nullable()->after('included_items');
            $table->json('process_steps')->nullable()->after('excluded_items');
            $table->json('equipment')->nullable()->after('process_steps');
            $table->json('duration_note')->nullable()->after('equipment');
            $table->json('price_factors')->nullable()->after('duration_note');
            $table->json('faq')->nullable()->after('price_factors');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'who_its_for',
                'included_items',
                'excluded_items',
                'process_steps',
                'equipment',
                'duration_note',
                'price_factors',
                'faq',
            ]);
        });
    }
};
