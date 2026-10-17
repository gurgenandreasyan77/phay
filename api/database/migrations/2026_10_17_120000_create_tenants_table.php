<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('default_locale', 5)->default('hy');
            $table->json('locales');
            $table->string('currency', 3)->default('AMD');
            $table->string('timezone')->default('Asia/Yerevan');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // The business that owns everything created before multi-tenancy
        // (see config/platform.php). Created even on an empty database so
        // a fresh install and the test suite always have it.
        DB::table('tenants')->insert([
            'name' => 'Servio',
            'slug' => config('platform.default_tenant'),
            'default_locale' => 'hy',
            'locales' => json_encode(['hy', 'ru', 'en']),
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
