<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a business pays for the admin, made by the super admin: a
        // monthly price, the free trial a new business gets, and the modules
        // whose price is already inside it. One plan is the default one new
        // businesses start on.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            // AMD per month.
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('trial_days')->default(14);
            // Feature flag keys (App\Features\FeatureFlag) the price includes.
            $table->json('included_modules');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The monthly price of each module, added to the plan's price when
        // the module is on for a business (and not included in its plan).
        Schema::create('module_prices', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 40)->unique();
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();
        });

        // One subscription per business. Access lasts until the latest of the
        // trial, the paid period and the free access the super admin gave,
        // unless it is unlimited, or the super admin turned it off from
        // cancel_at. Nothing of the business is deleted when it ends.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('paid_until')->nullable();
            $table->timestamp('free_until')->nullable();
            $table->boolean('unlimited')->default(false);
            $table->timestamp('cancel_at')->nullable();
            // The end date the business was already warned about / told has
            // passed, so each message goes once per end date.
            $table->timestamp('warned_for')->nullable();
            $table->timestamp('expired_notified_for')->nullable();
            $table->timestamps();
        });

        // Payments the super admin records by hand (no payment gateway yet).
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedSmallInteger('months');
            // The stretch it paid for.
            $table->timestamp('period_from');
            $table->timestamp('period_to');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'id']);
        });

        $now = now();

        $planId = DB::table('plans')->insertGetId([
            'name' => 'Բազային',
            'description' => 'Պատվերներ, վաճառքներ, ծախսեր, վիճակագրություն, վերլուծություն և AI օգնական',
            'price' => 3900,
            'trial_days' => 14,
            'included_modules' => json_encode(['orders']),
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // The default business never pays; the businesses already on the platform
        // start their trial today.
        $default = config('platform.default_tenant');

        foreach (DB::table('tenants')->get(['id', 'slug']) as $tenant) {
            $own = $tenant->slug === $default;

            DB::table('subscriptions')->insert([
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'trial_ends_at' => $own ? null : $now->copy()->addDays(14),
                'unlimited' => $own,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('module_prices');
        Schema::dropIfExists('plans');
    }
};
