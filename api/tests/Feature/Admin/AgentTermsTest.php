<?php

namespace Tests\Feature\Admin;

use App\Agents\AgentTerms;
use App\Assistant\AssistantClient;
use App\Models\AdminNotification;
use App\Models\AgentPayment;
use App\Models\Plan;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Each AI agent on its own term: a 7-day trial once, months paid one agent
 * at a time, and paying the difference so several agents end on one day.
 * The owner asks, the super admin records the money.
 */
class AgentTermsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AssistantClient::class, new FakeAssistantClient);
        // Times are kept in whole seconds; noon keeps "today" the same day in Yerevan.
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00:00', 'UTC'));
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function agent(string $key): array
    {
        return collect($this->getJson('/api/v1/admin/agents')->assertOk()->json('data.agents'))->firstWhere('key', $key);
    }

    public function test_the_owner_starts_a_seven_day_trial_once_and_the_agent_stops_when_it_ends(): void
    {
        Sanctum::actingAs($this->owner);

        $content = $this->agent('content');
        $this->assertSame('off', $content['term']['status']);
        $this->assertTrue($content['term']['trialAvailable']);

        $this->postJson('/api/v1/admin/agents/content/trial')->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.term.status', 'trial')
            ->assertJsonPath('data.term.daysLeft', 7)
            ->assertJsonPath('data.term.span', 7)
            ->assertJsonPath('data.term.trialAvailable', false);
        $this->assertTrue(SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', '%7 օրն անվճար%')->exists());

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրիր գրառում', 'agent' => 'content'])->assertOk();
        // Only once.
        $this->postJson('/api/v1/admin/agents/content/trial')->assertUnprocessable()->assertJsonValidationErrors('agent');

        // Ending soon: warned once, 3 days before.
        $this->travel(5)->days();
        $this->assertTrue($this->agent('content')['term']['ending']);
        $this->getJson('/api/v1/admin/agents')->assertOk();
        $this->assertSame(1, AdminNotification::query()->where('type', 'agent')->where('url', '/admin/subscription#agents')->count());

        $this->travel(3)->days();
        $this->assertSame('expired', $this->agent('content')['term']['status']);
        $this->assertFalse($this->agent('content')['active']);
        $this->assertTrue($this->agent('content')['onTeam']);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Եվս մեկը', 'agent' => 'content'])
            ->assertForbidden()
            ->assertJsonPath('code', 'agent_expired');
        $this->getJson('/api/v1/admin/agents')->assertOk();
        $this->assertSame(1, SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', 'Ավարտվեց AI աշխատակիցների ժամկետը%')->count());

        // Not even the super admin turning it off and on gives a second trial.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/content", ['active' => false])->assertOk();
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/content", ['active' => true])->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.term.status', 'expired');
    }

    public function test_the_super_admin_turning_an_agent_on_starts_its_trial(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/analyst", ['active' => true])->assertOk()
            ->assertJsonPath('data.term.status', 'trial')
            ->assertJsonPath('data.term.daysLeft', 7);
    }

    public function test_a_month_costs_the_price_and_a_part_of_one_is_priced_by_the_day(): void
    {
        $from = Carbon::parse('2026-11-02 08:00:00');

        $this->assertSame(2900, AgentTerms::amount(2900, $from, $from->copy()->addMonthNoOverflow()));
        // 10 days: 2900 × 10 / 30 = 966.67 → 970.
        $this->assertSame(970, AgentTerms::amount(2900, $from, $from->copy()->addDays(10)));
        $this->assertSame(2900 * 2 + 970, AgentTerms::amount(2900, $from, $from->copy()->addMonthsNoOverflow(2)->addDays(10)));
        $this->assertSame(0, AgentTerms::amount(2900, $from, $from));
    }

    public function test_the_owner_asks_to_pay_so_chosen_agents_end_on_one_day_and_the_super_admin_confirms(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/agents/content/trial')->assertCreated();
        $this->travel(4)->days();
        $this->postJson('/api/v1/admin/agents/analyst/trial')->assertCreated();

        // content ends on Nov 9, analyst on Nov 13: both to Nov 13, plus a month.
        $quote = $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => ['content', 'analyst'], 'until' => '2026-11-13', 'months' => 1])
            ->assertOk()
            ->assertJsonCount(2, 'data.lines')
            ->json('data');
        [$content, $analyst] = $quote['lines'];
        $this->assertSame('content', $content['agent']);
        // Nov 9 → Dec 13 by calendar days: a month, then 4 days (4 × 2900 / 30 = 386.67 → 390).
        $this->assertSame(2900 + 390, $content['amount']);
        $this->assertSame(34, $content['days']);
        // Nov 13 → Dec 13: a month; the hours to the end of Nov 13 cost nothing.
        $this->assertSame(2900, $analyst['amount']);
        $this->assertSame($content['to'], $analyst['to']);
        $this->assertSame('2026-12-13T19:59:59+00:00', $content['to']);
        $this->assertSame($content['amount'] + $analyst['amount'], $quote['total']);

        // A day before an agent's own end can't be the common one.
        $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => ['content', 'analyst'], 'until' => '2026-11-10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('until');

        // The request goes to the chat; a new one replaces the open one.
        $this->postJson('/api/v1/admin/agents/billing/request', ['agents' => ['content'], 'months' => 1])->assertCreated();
        $id = $this->postJson('/api/v1/admin/agents/billing/request', ['agents' => ['content', 'analyst'], 'until' => '2026-11-13', 'months' => 1])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.amount', $quote['total'])
            ->json('data.id');
        $this->assertSame(1, AgentPayment::query()->where('status', 'requested')->count());
        $this->assertTrue(SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('from_platform', false)->where('body', 'like', '%նույն օրը%')->exists());
        $this->getJson('/api/v1/admin/subscription')->assertOk()
            ->assertJsonPath('data.agentBilling.request.id', $id)
            ->assertJsonPath('data.agentBilling.payments', []);

        // Only the super admin records the money.
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$id}/confirm")->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertOk()
            ->assertJsonPath('data.agentBilling.request.id', $id);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$id}/confirm", ['note' => 'Idram'])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.note', 'Idram');
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$id}/confirm")->assertUnprocessable();

        Sanctum::actingAs($this->owner);
        $this->assertSame('active', $this->agent('content')['term']['status']);
        $this->assertSame($this->agent('content')['term']['endsAt'], $this->agent('analyst')['term']['endsAt']);
        $this->getJson('/api/v1/admin/subscription')->assertOk()
            ->assertJsonPath('data.agentBilling.request', null)
            ->assertJsonPath('data.agentBilling.payments.0.id', $id);
        $this->assertTrue(SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('from_platform', true)->where('body', 'like', '%վճարումը ստացված է%')->exists());
    }

    public function test_the_super_admin_records_a_payment_for_a_new_agent_and_takes_back_the_latest(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments", ['agents' => ['designer'], 'months' => 2, 'amount' => 7000])
            ->assertCreated()
            ->assertJsonPath('data.amount', 7000)
            ->assertJsonPath('data.lines.0.days', 61);

        $row = TenantAgent::withoutTenancy()->where('tenant_id', $this->shop->id)->where('agent_key', 'designer')->first();
        $this->assertTrue($row->active);
        $this->assertSame('2027-01-02 08:00:00', $row->paid_until->format('Y-m-d H:i:s'));

        $second = $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments", ['agents' => ['designer'], 'months' => 1])
            ->assertCreated()
            ->assertJsonPath('data.amount', 3900)
            ->json('data.id');
        $first = AgentPayment::query()->where('id', '<', $second)->value('id');

        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$first}")->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/tenants/{$this->shop->id}/agent-payments/{$second}")->assertOk();
        $this->assertSame('2027-01-02 08:00:00', $row->refresh()->paid_until->format('Y-m-d H:i:s'));
    }

    public function test_an_included_agent_never_ends_and_is_not_paid_for(): void
    {
        $plan = Plan::default();
        $plan->forceFill(['included_modules' => ['agent:analyst']])->save();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/analyst", ['active' => true])->assertOk()
            ->assertJsonPath('data.term.status', 'included')
            ->assertJsonPath('data.term.endsAt', null);

        // Long past a trial (the subscription itself still runs).
        $this->travel(12)->days();
        Sanctum::actingAs($this->owner);
        $this->assertTrue($this->agent('analyst')['active']);
        $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => ['analyst'], 'months' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agents');
    }

    public function test_only_the_business_owner_or_admin_asks_to_pay(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => ['content'], 'months' => 1])->assertForbidden();
        $this->postJson('/api/v1/admin/agents/content/trial')->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => []])->assertUnprocessable();
        $this->postJson('/api/v1/admin/agents/billing/quote', ['agents' => ['nobody'], 'months' => 1])->assertUnprocessable();

        // Someone else's request can't be canceled.
        $other = $this->createTenant('other-shop');
        $payment = AgentPayment::create(['tenant_id' => $other->id, 'status' => 'requested', 'amount' => 1, 'lines' => []]);
        $this->deleteJson("/api/v1/admin/agents/billing/request/{$payment->id}")->assertNotFound();
    }
}
