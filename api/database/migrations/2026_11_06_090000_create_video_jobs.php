<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Videos an AI agent cuts from the owner's clips (App\Media\VideoStudio):
 * the plan, its job in the media service, and the finished MP4 once it is
 * collected. A video clip attached in the chat keeps what was learned of
 * it (length, size, sound, a few frames) in assistant_attachments.meta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_attachments', function (Blueprint $table) {
            // A video's {duration, width, height, audio, frames: [{t, path}]}.
            $table->json('meta')->nullable()->after('html');
        });

        Schema::create('video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The answer it belongs to; null until the answer is saved.
            $table->foreignId('assistant_message_id')->nullable()->constrained()->cascadeOnDelete();
            // The finished MP4, once collected from the media service.
            $table->foreignId('attachment_id')->nullable()->constrained('assistant_attachments')->nullOnDelete();
            $table->string('media_job', 64)->nullable();
            // rendering, done or failed.
            $table->string('status', 16)->default('rendering');
            $table->string('title', 120);
            // reel, feed, square or landscape (App\Media\VideoFormat).
            $table->string('format', 16);
            $table->json('plan');
            $table->decimal('duration', 6, 2)->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_jobs');
        Schema::table('assistant_attachments', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
