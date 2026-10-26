<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pictures an AI agent made in the chat (App\Media\DesignStudio): a PNG
 * rendered from the agent's HTML, kept as a file of its answer. The HTML
 * stays with it, so the agent can change the design the owner picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_attachments', function (Blueprint $table) {
            // upload (the person attached it) or design (an agent made it).
            $table->string('origin', 16)->default('upload')->after('kind');
            $table->string('title', 120)->nullable()->after('name');
            // post, story, square... (App\Media\DesignFormat).
            $table->string('format', 16)->nullable()->after('title');
            $table->text('html')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_attachments', function (Blueprint $table) {
            $table->dropColumn(['origin', 'title', 'format', 'html']);
        });
    }
};
