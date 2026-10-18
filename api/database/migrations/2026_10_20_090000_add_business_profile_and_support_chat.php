<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The business's own logo (a public URL of an uploaded image), shown
        // in its admin and in the platform's list of businesses.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo', 500)->nullable()->after('name');
        });

        // One conversation per business with the platform team: plain text
        // messages, read by polling (no realtime).
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // true: written by the platform team, false: by the business.
            $table->boolean('from_platform');
            $table->text('body');
            // When the other side opened it; null = unread.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'id']);
            $table->index(['tenant_id', 'from_platform', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
