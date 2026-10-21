<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A person's Google account, connected to one business (read only).
        Schema::create('google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            // Encrypted (the model's casts): never sent to the admin.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('scopes', 500)->nullable();
            // Google refused the tokens (access revoked): the person connects again.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        // The calendars of a connected account; the chosen ones are synced.
        Schema::create('google_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_connection_id')->constrained()->cascadeOnDelete();
            $table->string('calendar_id');
            $table->string('name');
            $table->string('color', 16)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('selected')->default(false);
            // Google's token for "what changed since": the next sync reads only changes.
            $table->text('sync_token')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['google_connection_id', 'calendar_id']);
        });

        // The events of the chosen calendars, as last synced (a copy, never written back).
        Schema::create('google_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('event_id');
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            // The business's local time, like data_table_rows.occurred_at.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('html_link', 1000)->nullable();
            // Google's `updated`: a changed event has a newer one.
            $table->string('google_updated', 40)->nullable();
            $table->timestamps();

            $table->unique(['google_calendar_id', 'event_id']);
            $table->index(['tenant_id', 'starts_at']);
        });

        // A row made from a Google event: which one, and its data as written then.
        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->string('external_id')->nullable();
            // A hash of the row's data when synced: another hash means it was edited by hand.
            $table->string('external_hash', 64)->nullable();
            $table->index(['data_table_id', 'external_id']);
        });

        // What a table was made from (null: the business's own, "google": its
        // calendars), and an older version kept when a newer one replaced it:
        // out of the calendar, analytics and AI, still in the tables list.
        Schema::table('data_tables', function (Blueprint $table) {
            $table->string('source', 20)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('data_tables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_id');
            $table->dropColumn(['source', 'archived_at']);
        });

        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropIndex(['data_table_id', 'external_id']);
            $table->dropColumn(['external_id', 'external_hash']);
        });

        Schema::dropIfExists('google_events');
        Schema::dropIfExists('google_calendars');
        Schema::dropIfExists('google_connections');
    }
};
