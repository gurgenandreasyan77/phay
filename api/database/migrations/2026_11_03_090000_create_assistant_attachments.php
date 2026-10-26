<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files in the AI chat (a logo, examples of posts, a price list) and the
 * pictures the business keeps for its AI agents (its logo and examples).
 * The files live on the private disk (storage/app/private), never under a
 * public URL; rows only point at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // null until the question it goes with is answered.
            $table->foreignId('assistant_message_id')->nullable()->constrained()->cascadeOnDelete();
            // image, pdf or text (App\Assistant\AttachmentKind).
            $table->string('kind', 16);
            $table->string('mime', 100);
            $table->string('name', 255);
            $table->unsignedInteger('size');
            $table->string('path', 255);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'assistant_message_id']);
        });

        Schema::create('brand_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // logo (one per business) or example.
            $table->string('kind', 16);
            $table->string('mime', 100);
            $table->string('name', 255);
            $table->unsignedInteger('size');
            $table->string('path', 255);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // What it shows or why it is liked, for the agents.
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_assets');
        Schema::dropIfExists('assistant_attachments');
    }
};
