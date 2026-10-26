<?php

use App\Agents\AgentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI agents: virtual staff (an analyst, an accountant, a content creator...)
 * a business subscribes to like a module. The agents themselves live in
 * code (App\Agents\AgentCatalog), so a new one needs no migration; here are
 * which ones each business has, what each learned about it, the business's
 * brand profile they all share, and which agent a conversation is with.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The agents a business has (the super admin turns them on, like modules).
        Schema::create('tenant_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // App\Agents\AgentCatalog key: analyst, accountant, content...
            $table->string('agent_key', 40);
            $table->boolean('active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            // What the agent was told to remember about this business.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'agent_key']);
        });

        // What every agent of a business knows about it: the owner fills it
        // in a form or the agents ask and save it in the chat.
        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('about')->nullable();
            $table->text('offer')->nullable();
            $table->text('audience')->nullable();
            $table->text('tone')->nullable();
            $table->text('visual_style')->nullable();
            // [{hex, name}]
            $table->json('colors')->nullable();
            $table->text('examples')->nullable();
            $table->text('avoid')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // null: the general AI assistant.
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->string('agent_key', 40)->nullable()->after('user_id');
            $table->index(['tenant_id', 'user_id', 'agent_key']);
        });

        $now = now();

        // Each agent's starting price; the super admin changes them.
        foreach (AgentCatalog::all() as $agent) {
            DB::table('module_prices')->insertOrIgnore([
                'feature_key' => $agent->priceKey(),
                'price' => $agent->defaultPrice(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Businesses that never pay (the default one) get the whole team.
        $unlimited = DB::table('subscriptions')->where('unlimited', true)->pluck('tenant_id');
        foreach ($unlimited as $tenantId) {
            foreach (AgentCatalog::all() as $agent) {
                DB::table('tenant_agents')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'agent_key' => $agent->key(),
                    'active' => true,
                    'activated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id', 'agent_key']);
            $table->dropColumn('agent_key');
        });
        Schema::dropIfExists('brand_profiles');
        Schema::dropIfExists('tenant_agents');
        DB::table('module_prices')->where('feature_key', 'like', 'agent:%')->delete();
    }
};
