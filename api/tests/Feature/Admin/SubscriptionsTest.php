<?php

namespace Tests\Feature\Admin;

use App\Features\FeatureFlag;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Subscriptions\Subscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/** Plans, trials, payments, free access, warnings and the lock after the end. */
class SubscriptionsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(12, 0));
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function messages(): array
    {
        return SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->orderBy('id')->pluck('body')->all();
    }

    public function test_a_new_business_starts_a_trial_on_the_default_plan_and_the_default_business_is_unlimited(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/admin/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'trial')
            ->assertJsonPath('data.subscription.plan.name', 'Բազային')
            ->assertJsonPath('data.subscription.daysLeft', 14)
            ->assertJsonPath('data.subscription.pricing.total', 3900);

        $this->getJson('/api/v1/admin/me')
            ->assertJsonPath('data.subscription.status', 'trial')
            ->assertJsonPath('data.subscription.locked', false);

        $this->assertTrue(Subscription::query()->where('tenant_id', Tenant::default()->id)->value('unlimited'));
    }

    public function test_the_price_adds_the_modules_the_business_has_on(): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['market' => 2000, 'site' => 1500, 'services' => 900]])
            ->assertOk()
            ->assertJsonFragment(['key' => 'market', 'price' => 2000]);
        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['nope' => 1]])->assertUnprocessable();
        // Orders and sales are not modules: nothing to price.
        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['orders' => 1]])->assertUnprocessable();

        Feature::for($this->shop)->activate('market');

        // Market is added to the plan; services and site are off.
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")
            ->assertOk()
            ->assertJsonPath('data.subscription.pricing.total', 3900 + 2000);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/subscription')->assertJsonPath('data.subscription.pricing.total', 5900);
    }

    public function test_the_super_admin_manages_plans(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/admin/plans', [
            'name' => 'Պրեմիում', 'price' => 9900, 'trial_days' => 30, 'included_modules' => ['services', 'site'], 'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.isDefault', true)->json('data.id');

        $this->assertSame(1, Plan::query()->where('is_default', true)->count());
        $this->patchJson("/api/v1/admin/plans/{$id}", ['price' => 8900])->assertOk()->assertJsonPath('data.price', 8900);

        // A plan a business is on can't be deleted, only turned off.
        $base = Plan::query()->where('name', 'Բազային')->first();
        $this->deleteJson("/api/v1/admin/plans/{$base->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/plans/{$id}")->assertNoContent();

        // A new business gets the default plan's trial.
        $this->patchJson("/api/v1/admin/plans/{$base->id}", ['trial_days' => 7])->assertOk();
        $new = $this->postJson('/api/v1/admin/tenants', ['name' => 'New Shop'])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/admin/tenants/{$new}/subscription")->assertJsonPath('data.subscription.daysLeft', 7);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/plans')->assertForbidden();
        $this->getJson('/api/v1/admin/subscriptions')->assertForbidden();
    }

    public function test_premium_is_the_top_plan_above_the_default_one(): void
    {
        Sanctum::actingAs($this->owner);
        // The default plan is not premium, even while it is the only one.
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.subscription.premium', false);
        $this->getJson('/api/v1/admin/subscription')->assertJsonPath('data.subscription.premium', false);

        Sanctum::actingAs($this->admin);
        $premium = $this->postJson('/api/v1/admin/plans', ['name' => 'Պրեմիում', 'price' => 9900, 'trial_days' => 0])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/subscription", ['plan_id' => $premium])->assertOk();

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.subscription.premium', true);

        // A pricier plan appears: the business is no longer on the top one.
        Plan::query()->create(['name' => 'Բիզնես', 'price' => 19900, 'trial_days' => 0, 'included_modules' => [], 'is_active' => true]);
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.subscription.premium', false);

        // The default business never sees the invitation.
        $this->assertTrue(app(Subscriptions::class)->isPremium(app(Subscriptions::class)->for(Tenant::default())));
    }

    public function test_free_access_is_given_and_extended_by_the_super_admin(): void
    {
        Sanctum::actingAs($this->admin);

        // After the 14 trial days: one more week, then one more month.
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/free", ['amount' => 1, 'unit' => 'week'])
            ->assertOk()
            ->assertJsonPath('data.subscription.daysLeft', 21);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/free", ['amount' => 1, 'unit' => 'month'])->assertOk();

        $expected = now()->addDays(21)->addMonthNoOverflow();
        $this->assertTrue($this->shop->subscription()->first()->free_until->equalTo($expected));
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/free", ['amount' => 1, 'unit' => 'year'])->assertUnprocessable();

        $this->travel(20)->days();
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertJsonPath('data.subscription.status', 'free');
        $this->assertStringContainsString('անվճար մուտք', $this->messages()[0]);
    }

    public function test_a_payment_extends_the_subscription_and_can_be_taken_back(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments", ['months' => 2, 'note' => 'Idram'])
            ->assertCreated()
            ->assertJsonPath('data.payments.0.amount', 7800)
            ->assertJsonPath('data.payments.0.months', 2);

        // Paid from the end of the trial.
        $trialEnd = $this->shop->subscription()->first()->trial_ends_at;
        $this->assertTrue($trialEnd->copy()->addMonthsNoOverflow(2)->equalTo($response->json('data.subscription.paidUntil')));

        $this->travel(15)->days();
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertJsonPath('data.subscription.status', 'active');

        $first = $response->json('data.payments.0.id');
        $second = $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments", ['months' => 1, 'amount' => 3000])
            ->assertCreated()->json('data.payments.0.id');

        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments/{$first}")->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments/{$second}")
            ->assertOk()
            ->assertJsonPath('data.subscription.paidUntil', $response->json('data.subscription.paidUntil'));
    }

    public function test_the_business_is_warned_once_then_locked_out_with_its_data_kept(): void
    {
        $this->asTenant($this->shop, fn () => SupportMessage::query()->count());
        Sanctum::actingAs($this->owner);

        // Not yet in the 10-day window.
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertSame([], $this->messages());

        $this->travel(5)->days();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertCount(1, $this->messages());
        $this->assertStringContainsString('ավարտվում է', $this->messages()[0]);
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.subscription.ending', true);

        $this->travel(10)->days();
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(402)->assertJsonPath('code', 'subscription_expired');
        $this->getJson('/api/v1/admin/data-tables')->assertStatus(402);
        $this->assertCount(2, $this->messages());
        $this->assertStringContainsString('ավարտվել է', $this->messages()[1]);

        // What stays open: who they are, the subscription and the chat.
        $this->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.subscription.locked', true);
        $this->getJson('/api/v1/admin/subscription')->assertOk()->assertJsonPath('data.subscription.status', 'expired');
        $this->getJson('/api/v1/admin/support/messages')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'renew'])->assertCreated();

        // The platform still gets in; a payment opens the admin again.
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/subscriptions?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant.id', $this->shop->id);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments", ['months' => 1])->assertCreated();

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->assertSame(Tenant::STATUS_ACTIVE, $this->shop->fresh()->status);
    }

    public function test_turning_off_gives_notice_and_can_be_undone(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments", ['months' => 3])->assertCreated();

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/cancel", [])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'canceling')
            ->assertJsonPath('data.subscription.daysLeft', 10);
        $this->assertStringContainsString('կանջատվի', last($this->messages()));

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/resume")
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'active');

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/cancel", ['days' => 0])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'expired');

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(402);

        // Unlimited access, e.g. for a partner.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/subscription", ['unlimited' => true])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'unlimited')
            ->assertJsonPath('data.subscription.endsAt', null);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
    }

    public function test_the_owner_asks_for_a_module_in_the_chat(): void
    {
        ModulePrice::create(['feature_key' => 'market', 'price' => 2000]);
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'module', 'module' => 'market'])->assertCreated();
        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'module'])->assertUnprocessable();

        $this->assertStringContainsString('Խանութ', $this->messages()[0]);

        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'premium'])->assertCreated();
        $this->assertStringContainsString('Պրեմիում', $this->messages()[1]);
        $this->getJson('/api/v1/admin/subscription')
            ->assertOk()
            ->assertJsonPath('data.assistant.used', 0)
            ->assertJsonPath('data.assistant.limit', 30)
            ->assertJsonPath('data.assistant.remaining', 30)
            ->assertJsonPath('data.subscription.pricing.modules.1.description', FeatureFlag::Market->description());

        $moderator = User::factory()->forTenant($this->shop, 'moderator')->create();
        Sanctum::actingAs($moderator);
        $this->getJson('/api/v1/admin/subscription')->assertOk();
        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'renew'])->assertForbidden();
    }

    public function test_the_daily_command_sends_the_warning_without_anyone_opening_the_admin(): void
    {
        $this->travel(5)->days();

        $this->artisan('platform:subscriptions:sync')->assertSuccessful();
        $this->artisan('platform:subscriptions:sync')->assertSuccessful();

        $this->assertCount(1, $this->messages());
    }
}
