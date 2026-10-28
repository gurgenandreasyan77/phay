<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\AgentPayment;
use App\Models\BalanceTransaction;
use App\Models\BusinessBalance;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The business balance: the super admin tops it up when a receipt comes in
 * the chat, the business pays from it (subscription, plan, module, AI
 * agents) and what ends renews from it on its own. The ledger only grows.
 */
class BalanceTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AssistantClient::class, new FakeAssistantClient);
        // Noon in Yerevan; the default plan is 3 900 dram with a 14-day trial (ends Nov 16).
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00', 'UTC'));
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function topUp(int $amount, ?string $note = null): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/balance/top-ups", ['amount' => $amount, 'note' => $note])->assertCreated();
        Sanctum::actingAs($this->owner);
    }

    private function subscription(): Subscription
    {
        return Subscription::query()->where('tenant_id', $this->shop->id)->firstOrFail();
    }

    private function balance(): int
    {
        return (int) BusinessBalance::query()->where('tenant_id', $this->shop->id)->value('amount');
    }

    private function said(string $like): bool
    {
        return SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', $like)->exists();
    }

    public function test_the_super_admin_tops_up_the_balance_and_the_business_sees_it_with_its_history(): void
    {
        $this->topUp(10000, 'Idram, անդորրագիր #42');

        $this->assertSame(10000, $this->balance());
        $this->assertTrue($this->said('Ձեր հաշվեկշիռը համալրվել է 10 000 դր-ով%'));

        $this->getJson('/api/v1/admin/balance')->assertOk()
            ->assertJsonPath('data.amount', 10000)
            ->assertJsonPath('data.autoRenew', true)
            ->assertJsonPath('data.toppedUp', 10000)
            ->assertJsonPath('data.transactions.0.type', 'top_up')
            ->assertJsonPath('data.transactions.0.balanceAfter', 10000)
            ->assertJsonPath('data.transactions.0.note', 'Idram, անդորրագիր #42')
            // The business doesn't see who on the platform did it.
            ->assertJsonMissingPath('data.transactions.0.by')
            // The trial ends on Nov 16 and the balance covers that month.
            ->assertJsonPath('data.upcoming.0.kind', 'subscription')
            ->assertJsonPath('data.upcoming.0.amount', 3900)
            ->assertJsonPath('data.upcoming.0.covered', true)
            ->assertJsonPath('data.months', 2);

        $this->getJson('/api/v1/admin/subscription')->assertOk()->assertJsonPath('data.balance.amount', 10000);

        // Only the super admin tops up.
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/balance/top-ups", ['amount' => 500])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/balance")->assertOk()
            ->assertJsonPath('data.transactions.0.by', $this->admin->name);
        // A wrong top-up is fixed by a new row, never below zero.
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/balance/corrections", ['amount' => -20000, 'note' => 'սխալ'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/balance/corrections", ['amount' => -1000, 'note' => 'Սխալ գումար'])
            ->assertCreated()
            ->assertJsonPath('data.amount', 9000)
            ->assertJsonPath('data.transactions.0.type', 'correction');
        $this->assertSame(2, BalanceTransaction::query()->where('tenant_id', $this->shop->id)->count());
    }

    public function test_the_ledger_is_never_changed_or_deleted(): void
    {
        $this->topUp(5000);
        $row = BalanceTransaction::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $row->update(['amount' => 1]);
    }

    public function test_the_owner_renews_the_subscription_from_the_balance_and_it_fails_whole_when_short(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('balance');
        $this->assertSame(0, SubscriptionPayment::query()->count());

        $this->topUp(10000);
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 2])
            ->assertCreated()
            ->assertJsonPath('data.amount', 10000 - 7800)
            ->assertJsonPath('data.transactions.0.type', 'charge')
            ->assertJsonPath('data.transactions.0.purpose', 'subscription')
            ->assertJsonPath('data.transactions.0.amount', -7800);

        // From the trial's end: Nov 16 + 2 months.
        $this->assertSame('2027-01-16 08:00:00', $this->subscription()->paid_until->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($this->said('Բաժանորդագրությունը երկարացվեց մինչև 16.01.2027%'));

        // Not enough for 1 more: nothing is charged, nothing extended.
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 1])->assertUnprocessable();
        $this->assertSame(2200, $this->balance());
        $this->assertSame(1, SubscriptionPayment::query()->count());

        // Platform staff never pay for a business.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 1])->assertForbidden();
    }

    public function test_the_subscription_renews_on_its_own_when_it_ends_and_a_top_up_brings_an_ended_one_back(): void
    {
        $this->topUp(5000);

        // A day before the trial's end the next request renews it.
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00', 'UTC'));
        $this->getJson('/api/v1/admin/me')->assertOk();
        $this->assertSame('2026-12-16 08:00:00', $this->subscription()->paid_until->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1100, $this->balance());
        $charge = BalanceTransaction::query()->where('type', 'charge')->firstOrFail();
        $this->assertTrue($charge->automatic);
        $this->assertTrue($this->said('Բաժանորդագրությունն ինքնաշխատ երկարացվեց մինչև 16.12.2026%'));

        // Twice in a row renews once.
        $this->getJson('/api/v1/admin/me')->assertOk();
        $this->assertSame(1, SubscriptionPayment::query()->count());

        // Next month the balance falls short: told once, then it ends.
        $this->travelTo(Carbon::parse('2026-12-15 10:00:00', 'UTC'));
        $this->getJson('/api/v1/admin/me')->assertOk();
        $this->getJson('/api/v1/admin/me')->assertOk();
        $this->assertSame(1, SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', 'Հաշվեկշիռը բավարար չէ%')->count());

        $this->travelTo(Carbon::parse('2026-12-17 10:00:00', 'UTC'));
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(402);
        // The balance stays open after the end.
        $this->getJson('/api/v1/admin/balance')->assertOk()->assertJsonPath('data.amount', 1100);

        // A top-up renews it right away, from today.
        $this->topUp(3000);
        $this->assertSame('active', $this->subscription()->status()->value);
        $this->assertSame('2027-01-17 10:00:00', $this->subscription()->paid_until->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(200, $this->balance());
    }

    public function test_auto_renew_can_be_turned_off(): void
    {
        $this->topUp(5000);
        $this->patchJson('/api/v1/admin/balance', ['auto_renew' => false])->assertOk()->assertJsonPath('data.autoRenew', false);

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(402);
        $this->assertSame(5000, $this->balance());
    }

    public function test_ai_agents_are_paid_from_the_balance_and_paid_ones_renew_on_their_own(): void
    {
        $this->topUp(20000);

        // A paid month of the content creator, from today.
        $this->postJson('/api/v1/admin/balance/agents', ['agents' => ['content'], 'months' => 1])
            ->assertCreated()
            ->assertJsonPath('data.amount', 20000 - 2900)
            ->assertJsonPath('data.transactions.0.purpose', 'agents');
        $content = TenantAgent::withoutTenancy()->where('tenant_id', $this->shop->id)->where('agent_key', 'content')->firstOrFail();
        $this->assertTrue($content->active);
        $this->assertSame('2026-12-02 08:00:00', $content->paid_until->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(1, AgentPayment::query()->where('status', 'paid')->count());

        // A trial is never turned into a payment on its own.
        $this->postJson('/api/v1/admin/agents/analyst/trial')->assertCreated();

        $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'UTC'));
        $this->getJson('/api/v1/admin/me')->assertOk();

        $this->assertSame('2027-01-02 08:00:00', $content->refresh()->paid_until->utc()->format('Y-m-d H:i:s'));
        $this->assertNull(TenantAgent::withoutTenancy()->where('tenant_id', $this->shop->id)->where('agent_key', 'analyst')->value('paid_until'));
        // The ended trial renewed the subscription today too: 20 000 − 2 900 − 3 900 − 2 900.
        $this->assertSame(10300, $this->balance());
        $this->assertTrue($this->said('AI աշխատակիցներն ինքնաշխատ երկարացվեցին%'));
    }

    public function test_a_top_up_pays_the_open_request_for_agents(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/agents/billing/request', ['agents' => ['content'], 'months' => 1])->assertCreated();

        $this->topUp(3000);

        $this->assertSame(0, AgentPayment::query()->where('status', 'requested')->count());
        $this->assertSame(1, AgentPayment::query()->where('status', 'paid')->count());
        $this->assertSame(100, $this->balance());
    }

    public function test_a_module_costs_the_days_left_of_the_paid_month_and_a_bigger_plan_the_difference(): void
    {
        ModulePrice::create(['feature_key' => 'market', 'price' => 3000]);
        Plan::create(['name' => 'Պրեմիում', 'price' => 9900, 'is_active' => true, 'assistant_requests' => 500, 'included_modules' => [], 'sort_order' => 2]);
        $premium = Plan::query()->where('name', 'Պրեմիում')->firstOrFail();

        // In the trial a module costs nothing now.
        $this->topUp(50000);
        $this->postJson('/api/v1/admin/balance/module', ['module' => 'site'])->assertCreated()->assertJsonPath('data.amount', 50000);
        $this->assertTrue(Feature::for($this->shop)->active('site'));
        $this->postJson('/api/v1/admin/balance/module', ['module' => 'site'])->assertUnprocessable();

        // Paid until Dec 16: Nov 2 → Dec 2 a month, then 14 days (3000 × 14 / 30 = 1400).
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 1])->assertCreated();
        $this->postJson('/api/v1/admin/balance/module', ['module' => 'market'])
            ->assertCreated()
            ->assertJsonPath('data.transactions.0.amount', -4400);
        $this->assertTrue(Feature::for($this->shop)->active('market'));

        // Premium: the difference of the plan price (6000) for the same days.
        $before = $this->balance();
        $this->postJson('/api/v1/admin/balance/plan', ['plan_id' => $premium->id])->assertCreated()
            ->assertJsonPath('data.transactions.0.purpose', 'plan')
            ->assertJsonPath('data.transactions.0.amount', -8800);
        $this->assertSame($before - 8800, $this->balance());
        $this->assertTrue($this->subscription()->plan->is($premium));

        // A smaller plan stays a request in the chat.
        $this->postJson('/api/v1/admin/balance/plan', ['plan_id' => Plan::default()->id])->assertUnprocessable();
    }

    public function test_taking_back_a_payment_made_from_the_balance_returns_the_money(): void
    {
        $this->topUp(10000);
        $this->postJson('/api/v1/admin/balance/subscription', ['months' => 1])->assertCreated();
        $this->postJson('/api/v1/admin/balance/agents', ['agents' => ['content'], 'months' => 1])->assertCreated();
        $this->assertSame(10000 - 3900 - 2900, $this->balance());

        Sanctum::actingAs($this->admin);
        $agentPayment = AgentPayment::query()->where('status', 'paid')->firstOrFail();
        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$agentPayment->id}")->assertOk();
        $payment = SubscriptionPayment::query()->firstOrFail();
        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments/{$payment->id}")->assertOk();

        $this->assertSame(10000, $this->balance());
        $this->assertSame(2, BalanceTransaction::query()->where('type', 'refund')->count());
        $this->assertNull($this->subscription()->paid_until);
    }
}
