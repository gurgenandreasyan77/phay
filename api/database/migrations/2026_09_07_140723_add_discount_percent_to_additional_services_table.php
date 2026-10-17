<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            // Percent (0-100) knocked off price_amount, same "was/now" treatment
            // as CalculatorSetting::cleaning_discounts. Defaults to 0 at the DB
            // level so existing rows need no backfill.
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('price_unit');
        });
    }

    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }
};
