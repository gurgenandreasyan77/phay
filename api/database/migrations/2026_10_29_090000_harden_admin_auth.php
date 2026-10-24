<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in hardening: invited logins without a password until they set one,
 * two-factor authentication (TOTP + recovery codes), verified email and
 * phone, one-time links (invitation, password reset), verification codes,
 * where each session signed in from, and an audit log of security events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null until an invited person sets their own password.
            $table->string('password')->nullable()->change();
            $table->string('phone', 32)->nullable()->after('email_verified_at');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            // Encrypted with APP_KEY (see User casts).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // The last 30-second step a code was accepted for: a code works once.
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            $table->timestamp('password_changed_at')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            // The owner asks every person of the business to use 2FA.
            $table->boolean('require_two_factor')->default(false);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
        });

        Schema::create('account_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            // sha256 of the token in the link; the token itself is never stored.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            // Where the code went (the email, or the new phone being confirmed).
            $table->string('destination');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Who did it, when not the person themselves (staff, the console).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Typed email of a failed sign-in, kept even for unknown logins.
            $table->string('email')->nullable();
            $table->string('event', 48)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('account_tokens');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('require_two_factor');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'phone_verified_at', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'two_factor_last_step', 'password_changed_at',
            ]);
        });
    }
};
