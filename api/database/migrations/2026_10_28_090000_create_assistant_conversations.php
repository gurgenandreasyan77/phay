<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each person's chats with the AI assistant inside a business, kept
        // so they can be read again and continued.
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'updated_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_conversation_id')->constrained()->cascadeOnDelete();
            // user: the question; assistant: the answer.
            $table->string('role', 16);
            $table->text('content');
            // The model declined to answer.
            $table->boolean('refused')->default(false);
            $table->timestamps();

            $table->index(['assistant_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
