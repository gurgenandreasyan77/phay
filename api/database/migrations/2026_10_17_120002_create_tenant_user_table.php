<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // owner | editor (permissions per role arrive with the
            // business/site screens).
            $table->string('role')->default('owner');
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_tenant_id')->nullable()->after('is_super_admin')
                ->constrained('tenants')->nullOnDelete();
        });

        // Everyone who could use the admin before keeps working on the
        // original business.
        $tenantId = DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id');

        DB::table('users')->orderBy('id')->each(function ($user) use ($tenantId) {
            DB::table('tenant_user')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_tenant_id');
        });

        Schema::dropIfExists('tenant_user');
    }
};
