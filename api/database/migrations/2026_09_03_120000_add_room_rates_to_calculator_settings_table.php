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
            // { studio: { regular, general, postConstruction }, oneRoom: {...}, twoRoom: {...}, threeRoom: {...}, fourPlusRoom: {...} }
            // Flat "from" prices shown per room count, independent of the m²-based cleaning_rates above.
            $table->json('room_rates')->nullable()->after('cleaning_rates');
        });

        // Backfill the pre-existing singleton row (CalculatorSetting::current()'s
        // firstOrCreate only sets defaults when the row doesn't exist yet, so a
        // row created before this migration would otherwise be left with a null
        // room_rates and a broken room-based calculator block).
        DB::table('calculator_settings')->whereNull('room_rates')->update([
            'room_rates' => json_encode([
                'studio' => ['regular' => 9000, 'general' => 14000, 'postConstruction' => 22000],
                'oneRoom' => ['regular' => 14000, 'general' => 20000, 'postConstruction' => 32000],
                'twoRoom' => ['regular' => 25000, 'general' => 34000, 'postConstruction' => 52000],
                'threeRoom' => ['regular' => 34000, 'general' => 46000, 'postConstruction' => 70000],
                'fourPlusRoom' => ['regular' => 45000, 'general' => 60000, 'postConstruction' => 90000],
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('calculator_settings', function (Blueprint $table) {
            $table->dropColumn('room_rates');
        });
    }
};
