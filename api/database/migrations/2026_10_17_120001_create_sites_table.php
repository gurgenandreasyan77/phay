<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('public_key')->unique();
            $table->json('allowed_origins')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $tenantId = DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id');

        DB::table('sites')->insert([
            'tenant_id' => $tenantId,
            'name' => 'servio.am',
            'domain' => 'servio.am',
            'public_key' => 'site_pk_'.Str::random(32),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
