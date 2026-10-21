<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Questions to the AI assistant a business on the plan may ask in one
        // subscription month; null: no limit, 0: no assistant on this plan.
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('assistant_requests')->nullable()->default(30)->after('trial_days');
        });

        // Usage is counted per subscription month (from the day the business
        // started paying), not per calendar month: the row is keyed by the
        // day that month started. bonus_used: requests of that month paid
        // with bonus requests, once the plan's ones ran out.
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('tenant_id');
            $table->unsignedInteger('bonus_used')->default(0)->after('requests');
        });

        DB::table('assistant_usages')->orderBy('id')->each(function ($usage) {
            DB::table('assistant_usages')->where('id', $usage->id)->update(['period_start' => $usage->month.'-01']);
        });

        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'month']);
        });
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->dropColumn('month');
        });
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->unique(['tenant_id', 'period_start']);
        });

        // Extra requests the super admin gives a business by hand (a negative
        // amount takes some back). They don't expire: they are spent after
        // the plan's requests of a month run out.
        Schema::create('assistant_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('amount');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_bonuses');

        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->string('month', 7)->nullable();
        });
        DB::table('assistant_usages')->orderBy('id')->each(function ($usage) {
            DB::table('assistant_usages')->where('id', $usage->id)->update(['month' => substr((string) $usage->period_start, 0, 7)]);
        });
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'period_start']);
        });
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'bonus_used']);
        });
        Schema::table('assistant_usages', function (Blueprint $table) {
            $table->unique(['tenant_id', 'month']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('assistant_requests');
        });
    }
};
