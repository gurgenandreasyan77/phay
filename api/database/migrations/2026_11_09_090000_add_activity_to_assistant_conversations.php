<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a chat with the assistant or an agent was last answered and when its
 * person last saw it (App\Assistant\RecentChats): the assistant page reopens
 * the chat they were in, and the header shows the agent they talked to, with
 * a mark for an answer that came while they were elsewhere.
 *
 * Kept chats count as answered and seen when they were last updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->index(['tenant_id', 'user_id', 'answered_at']);
            $table->index(['tenant_id', 'user_id', 'read_at']);
        });

        DB::table('assistant_conversations')->update([
            'answered_at' => DB::raw('updated_at'),
            'read_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id', 'answered_at']);
            $table->dropIndex(['tenant_id', 'user_id', 'read_at']);
            $table->dropColumn(['answered_at', 'read_at']);
        });
    }
};
