<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's balance in dram (App\Billing\Balance): the super admin tops
 * it up when a receipt arrives in the chat, and the business pays from it
 * for its subscription, a bigger plan, modules and AI agents, by hand or
 * automatically when a term ends. Every change is a row of the ledger,
 * which is only ever added to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            // Renew the subscription and paid AI agents from the balance when they end.
            $table->boolean('auto_renew')->default(true);
            // The end date the business was told the balance doesn't cover (once each).
            $table->timestamp('short_notified_for')->nullable();
            $table->timestamps();
        });

        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // top_up, charge, refund or correction.
            $table->string('type', 20);
            // Signed: a top-up or a refund adds, a charge takes.
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            // What a charge paid for: subscription, plan, module or agents.
            $table->string('purpose', 20)->nullable();
            $table->string('description', 500);
            $table->string('note', 500)->nullable();
            // The subscription or agent payment it made (or gave back).
            $table->nullableMorphs('reference');
            $table->json('meta')->nullable();
            // Done by the platform on its own (an automatic renewal).
            $table->boolean('automatic')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
        Schema::dropIfExists('business_balances');
    }
};
