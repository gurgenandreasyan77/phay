<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The social module ("social"): a business connects its Instagram
 * professional account and Facebook Page through Meta (read only), their
 * figures and posts are kept here and refreshed on a schedule, and public
 * figures of competitors' Instagram accounts (Business Discovery) next to
 * them. The AI assistant reads all of it through its tools.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The Meta (Facebook) login a business connected: one per business.
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            // Who connected it (stays when that person is removed).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Meta's app-scoped id of that Facebook user: data deletion requests name it.
            $table->string('meta_user_id', 64)->index();
            $table->string('meta_user_name')->nullable();
            // Long-lived user token (about 60 days), encrypted by the model.
            $table->text('access_token');
            $table->timestamp('expires_at')->nullable();
            // Permissions the person granted (they can untick some on Meta's page).
            $table->text('scopes')->nullable();
            // Meta refused the token: the business connects again.
            $table->timestamp('revoked_at')->nullable();
            // The «connect again soon» notice was sent for this token.
            $table->timestamp('expiry_warned_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error', 500)->nullable();
            $table->timestamps();
        });

        // Facebook Pages and Instagram accounts the login can read; the selected ones are synced.
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_connection_id')->constrained()->cascadeOnDelete();
            // instagram | facebook
            $table->string('network', 20);
            $table->string('external_id', 64);
            // The Facebook Page an Instagram account is linked to (its token reads both).
            $table->string('page_id', 64)->nullable();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->text('picture_url')->nullable();
            // The Page's token (never expires while the login is valid), encrypted.
            $table->text('page_token')->nullable();
            $table->boolean('selected')->default(false);
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('follows')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            // Bio, website, category, link.
            $table->json('profile')->nullable();
            // Totals of the last 28 days and of the 28 before.
            $table->json('insights')->nullable();
            // Followers by age, gender, city, country (Meta gives them from 100 followers).
            $table->json('audience')->nullable();
            $table->timestamp('audience_synced_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['social_connection_id', 'network', 'external_id']);
        });

        // Posts of the selected accounts with their figures, as last synced.
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            // IMAGE, VIDEO, CAROUSEL_ALBUM, REELS (Instagram), post (Facebook).
            $table->string('kind', 30)->nullable();
            $table->text('caption')->nullable();
            $table->string('permalink', 1000)->nullable();
            $table->timestamp('posted_at');
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('interactions')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['social_account_id', 'posted_at']);
        });

        // Followers of an account each day it was synced: the growth over time.
        Schema::create('social_account_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'date']);
        });

        // Instagram accounts the business watches: only their public figures.
        Schema::create('social_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 40);
            $table->string('name')->nullable();
            $table->text('biography')->nullable();
            $table->string('website', 500)->nullable();
            $table->text('picture_url')->nullable();
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('follows')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            // Averages of the recent posts (App\Social\PostStats).
            $table->json('stats')->nullable();
            $table->json('recent_posts')->nullable();
            // pending | ok | not_found | error
            $table->string('status', 20)->default('pending');
            $table->string('error', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'username']);
        });

        Schema::create('social_competitor_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_competitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            $table->timestamps();

            $table->unique(['social_competitor_id', 'date']);
        });

        // Meta's deauthorize and data deletion callbacks: what was deleted,
        // and the code the person can check it with. The Facebook user id is
        // kept only as a hash.
        Schema::create('meta_data_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('confirmation_code', 40)->unique();
            // deauthorize | deletion
            $table->string('kind', 20);
            $table->string('meta_user_hash', 64)->index();
            $table->unsignedInteger('connections')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_data_deletions');
        Schema::dropIfExists('social_competitor_days');
        Schema::dropIfExists('social_competitors');
        Schema::dropIfExists('social_account_days');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('social_connections');
    }
};
