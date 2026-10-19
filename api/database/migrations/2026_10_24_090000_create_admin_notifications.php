<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The bell in the admin's header: one row per person, read by polling.
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The business it is about (null: the platform itself).
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            // business: shown inside that business; platform: on any page of platform staff.
            $table->string('scope', 16);
            $table->string('type', 40);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            // Sent once per key and person (e.g. one "today's orders" a day).
            $table->string('dedupe_key', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
