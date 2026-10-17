<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculator_settings', function (Blueprint $table) {
            // { regular: percentOff, general: percentOff, postConstruction: percentOff }
            // Percent (0-100) knocked off the per-m² cleaning_rates / room_rates
            // for that cleaning type, wherever its price is shown.
            $table->json('cleaning_discounts')->nullable()->after('room_rates');
        });

        // Backfill the pre-existing singleton row the same way room_rates was
        // backfilled — firstOrCreate only sets defaults when the row doesn't
        // exist yet, so a row created before this migration would otherwise
        // be left with a null cleaning_discounts.
        DB::table('calculator_settings')->whereNull('cleaning_discounts')->update([
            'cleaning_discounts' => json_encode([
                'regular' => 0,
                'general' => 0,
                'postConstruction' => 0,
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('calculator_settings', function (Blueprint $table) {
            $table->dropColumn('cleaning_discounts');
        });
    }
};
