<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each AI agent of a business runs on its own term: a 7-day trial once,
 * then months paid one agent at a time (App\Agents\AgentTerms). The owner
 * can pay the difference so several agents end on one day; until the
 * super admin confirms that it is a request (agent_payments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_agents', function (Blueprint $table) {
            // Set once: an agent's trial is never given twice.
            $table->timestamp('trial_ends_at')->nullable()->after('activated_by');
            $table->timestamp('paid_until')->nullable()->after('trial_ends_at');
            // The end date the business was warned about / told it ended (once each).
            $table->timestamp('warned_for')->nullable()->after('paid_until');
            $table->timestamp('expired_notified_for')->nullable()->after('warned_for');
        });

        Schema::create('agent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // requested: the owner asked to pay; paid: the super admin recorded it.
            $table->string('status', 20)->default('requested');
            $table->unsignedInteger('amount');
            // A common end day of every line (sync), or null: each agent from its own end.
            $table->timestamp('until')->nullable();
            $table->unsignedSmallInteger('months')->default(0);
            // [{agent, from, to, days, amount, previous_paid_until}]
            $table->json('lines');
            $table->string('note', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Agents already on a team had no term of their own: they start a trial today.
        DB::table('tenant_agents')->where('active', true)->whereNull('trial_ends_at')->update([
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payments');

        Schema::table('tenant_agents', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'paid_until', 'warned_for', 'expired_notified_for']);
        });
    }
};
