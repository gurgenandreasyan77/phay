<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\AssistantUsage;
use App\Models\Plan;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * AI assistant requests: a number per plan set by the super admin, counted
 * per subscription month (from the day the business started paying), bonus
 * requests given by hand, and what the business is offered when they run out.
 */
class AssistantQuotaTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(AssistantClient::class, new FakeAssistantClient);
        $this->travelTo(Carbon::parse('2026-09-01 10:00:00'));
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function ask()
    {
        return $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Ինչպե՞ս են վաճառքները']]]);
    }

    private function payOn(string $date, int $months = 1): void
    {
        $this->travelTo(Carbon::parse($date));
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/payments", ['months' => $months])->assertCreated();
    }

    public function test_the_super_admin_sets_requests_per_plan(): void
    {
        Sanctum::actingAs($this->admin);
        $plan = Plan::default();

        $this->getJson('/api/v1/admin/plans')->assertOk()->assertJsonPath('data.0.assistantRequests', 30);
        $this->patchJson("/api/v1/admin/plans/{$plan->id}", ['assistant_requests' => 2])->assertOk()->assertJsonPath('data.assistantRequests', 2);
        $this->postJson('/api/v1/admin/plans', ['name' => 'Պրեմիում', 'price' => 9900, 'assistant_requests' => 100])
            ->assertCreated()
            ->assertJsonPath('data.assistantRequests', 100);
        $this->patchJson("/api/v1/admin/plans/{$plan->id}", ['assistant_requests' => -1])->assertUnprocessable();

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/admin/plans/{$plan->id}", ['assistant_requests' => 1000])->assertForbidden();

        $this->ask()->assertOk()->assertJsonPath('data.remaining', 1);
        $this->ask()->assertOk()->assertJsonPath('data.remaining', 0);
        // Out of requests: the plans with more of them, and the day they renew.
        $this->ask()->assertStatus(429)
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('offer.plans.0.name', 'Պրեմիում')
            ->assertJsonPath('offer.plans.0.assistantRequests', 100)
            ->assertJsonPath('usage.resetsAt', Carbon::parse('2026-10-01 10:00:00')->toIso8601String());

        // Moved to the bigger plan: its limit counts at once.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/subscription", ['plan_id' => Plan::query()->where('name', 'Պրեմիում')->value('id')])->assertOk();
        Sanctum::actingAs($this->owner);
        $this->ask()->assertOk()->assertJsonPath('data.used', 3)->assertJsonPath('data.remaining', 97);
    }

    public function test_the_month_runs_from_the_day_the_business_paid(): void
    {
        Plan::default()->update(['assistant_requests' => 5]);

        // The trial (from 1 September) ended; paid on 20 September.
        $this->payOn('2026-09-20 15:00:00');
        Sanctum::actingAs($this->owner);
        $this->ask()->assertOk();
        $this->getJson('/api/v1/admin/assistant')
            ->assertJsonPath('data.periodStart', Carbon::parse('2026-09-20 15:00:00')->toIso8601String())
            ->assertJsonPath('data.resetsAt', Carbon::parse('2026-10-20 15:00:00')->toIso8601String())
            ->assertJsonPath('data.remaining', 4);

        // Paid again before the end: the same day of the month goes on.
        $this->payOn('2026-10-15 09:00:00');
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
        Sanctum::actingAs($this->owner);
        $this->ask()->assertOk()->assertJsonPath('data.used', 2);

        // On the 20th the requests are new; not on the 1st of the month.
        $this->travelTo(Carbon::parse('2026-10-20 16:00:00'));
        $this->getJson('/api/v1/admin/assistant')
            ->assertJsonPath('data.used', 0)
            ->assertJsonPath('data.remaining', 5)
            ->assertJsonPath('data.resetsAt', Carbon::parse('2026-11-20 15:00:00')->toIso8601String());

        $this->assertSame(['2026-09-20' => 2], AssistantUsage::withoutTenancy()->get()->mapWithKeys(fn ($u) => [$u->period_start->toDateString() => $u->requests])->all());
    }

    public function test_bonus_requests_are_spent_after_the_plan_ones_and_do_not_expire(): void
    {
        Plan::default()->update(['assistant_requests' => 1]);
        Sanctum::actingAs($this->admin);
        // Free access keeps the months counted from the trial's start.
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/free", ['amount' => 3, 'unit' => 'month'])->assertOk();

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/assistant-bonus", ['amount' => 2, 'note' => 'Նվեր'])
            ->assertCreated()
            ->assertJsonPath('data.assistant.bonus', 2)
            ->assertJsonPath('data.assistant.remaining', 3)
            ->assertJsonPath('data.assistant.bonuses.0.note', 'Նվեր');
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/assistant-bonus", ['amount' => 0])->assertUnprocessable();
        $this->assertStringContainsString('+2', SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->latest('id')->value('body'));

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/assistant-bonus", ['amount' => 5])->assertForbidden();

        $this->ask()->assertOk()->assertJsonPath('data.bonus', 2)->assertJsonPath('data.remaining', 2);
        $this->ask()->assertOk()->assertJsonPath('data.bonus', 1)->assertJsonPath('data.limit', 3);

        // Next month: the plan's request again, and the bonus one still there.
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00'));
        $this->getJson('/api/v1/admin/assistant')
            ->assertJsonPath('data.used', 0)
            ->assertJsonPath('data.bonus', 1)
            ->assertJsonPath('data.remaining', 2);

        // Taking back more than is left takes what is left.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/subscription/assistant-bonus", ['amount' => -10])
            ->assertCreated()
            ->assertJsonPath('data.assistant.bonus', 0)
            ->assertJsonPath('data.assistant.bonuses.0.amount', -1);
    }

    public function test_an_unlimited_business_has_no_limit(): void
    {
        Plan::default()->update(['assistant_requests' => 0]);
        Sanctum::actingAs(User::factory()->forTenant(Tenant::default())->create());

        $this->ask()->assertOk()
            ->assertJsonPath('data.unlimited', true)
            ->assertJsonPath('data.remaining', null);

        // A plan with 0 requests has no assistant: the business is offered more.
        Sanctum::actingAs($this->owner);
        $this->ask()->assertStatus(429)->assertJsonPath('usage.planLimit', 0);
    }

    public function test_the_business_asks_for_more_requests_in_the_chat(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'assistant'])->assertCreated();
        $this->assertStringContainsString('AI օգնական', SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->latest('id')->value('body'));
    }
}
