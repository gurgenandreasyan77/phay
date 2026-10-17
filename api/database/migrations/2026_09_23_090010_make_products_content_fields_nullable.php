<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A true draft SKU template (see market plan section 5) has a name but
     * no confirmed description, photo, or alt text yet — these columns were
     * NOT NULL from the original MVP schema, which assumed every row had
     * real content. Relaxing them lets a draft honestly represent "not
     * filled in yet" as NULL instead of an empty-string placeholder.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('description')->nullable()->change();
            $table->json('alt')->nullable()->change();
            $table->string('image')->nullable()->change();
            $table->unsignedInteger('price_amount')->nullable()->change();
            // `slug` is now a legacy convenience column (kept only for old
            // lead correlation, see LeadService) auto-derived from slug_en/
            // slug_hy when a product is created through the admin API — it's
            // no longer the field admins edit, so it shouldn't be mandatory
            // for every row created directly against the model.
            $table->string('slug')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('description')->nullable(false)->change();
            $table->json('alt')->nullable(false)->change();
            $table->string('image')->nullable(false)->change();
            $table->unsignedInteger('price_amount')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
        });
    }
};
