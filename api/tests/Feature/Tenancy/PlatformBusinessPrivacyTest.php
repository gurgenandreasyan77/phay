<?php

namespace Tests\Feature\Tenancy;

use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Platform staff (super admin, platform moderator) manages a business from
 * the platform level: its activity, subscription, modules and AI usage, but
 * never reads its orders, sales, money or conversations, unless it is one
 * of that business's own people.
 */
class PlatformBusinessPrivacyTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
        $this->asTenant($this->shop, fn () => Lead::create([
            'name' => 'Shop client',
            'phone' => '+37491000000',
            'preferred_locale' => 'hy',
            'consent' => true,
        ]));
    }

    /** Staff who isn't a member of the shop, "inside" it as its current business. */
    private function staffInShop(string $kind = 'superAdmin'): User
    {
        $user = User::factory()->withoutTenant()->{$kind}()->create();
        $user->forceFill(['current_tenant_id' => $this->shop->id])->save();

        return Sanctum::actingAs($user);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function contentEndpoints(): array
    {
        return [
            ['GET', '/api/v1/admin/dashboard'],
            ['GET', '/api/v1/admin/leads'],
            ['GET', '/api/v1/admin/calendar'],
            ['GET', '/api/v1/admin/data-tables'],
            ['GET', '/api/v1/admin/employees'],
            ['GET', '/api/v1/admin/analytics'],
            ['GET', '/api/v1/admin/analytics/tables'],
            ['GET', '/api/v1/admin/analytics/statistics'],
            ['GET', '/api/v1/admin/analytics/finance'],
            ['GET', '/api/v1/admin/expense-categories'],
            ['GET', '/api/v1/admin/expenses/statistics'],
            ['GET', '/api/v1/admin/assistant'],
            ['GET', '/api/v1/admin/assistant/conversations'],
            ['GET', '/api/v1/admin/agents'],
            ['GET', '/api/v1/admin/settings'],
            ['GET', '/api/v1/admin/products'],
            ['POST', '/api/v1/admin/data-tables'],
            ['POST', '/api/v1/admin/assistant'],
        ];
    }

    #[DataProvider('contentEndpoints')]
    public function test_a_super_admin_reads_none_of_a_business_data(string $method, string $uri): void
    {
        $this->staffInShop();

        $this->json($method, $uri)->assertForbidden();
    }

    public function test_a_platform_moderator_reads_none_of_a_business_data_either(): void
    {
        $this->staffInShop('platformModerator');

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.currentTenant.slug', 'test-shop')
            ->assertJsonPath('data.businessRole', null)
            ->assertJsonPath('data.permissions', []);
        $this->getJson('/api/v1/admin/leads')->assertForbidden();
        $this->getJson('/api/v1/admin/data-tables')->assertForbidden();
    }

    public function test_the_header_search_finds_businesses_but_no_business_data_for_staff(): void
    {
        $this->staffInShop();

        $groups = collect($this->getJson('/api/v1/admin/search?q=shop')->assertOk()->json('data.groups'))->pluck('key');

        $this->assertSame(['businesses'], $groups->all());
    }

    public function test_staff_that_is_also_a_member_of_the_business_still_sees_none_of_its_figures(): void
    {
        // E.g. a super admin account that is also the owner of the default business.
        $user = User::factory()->withoutTenant()->superAdmin()->forTenant($this->shop, 'owner')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/me')
            ->assertJsonPath('data.businessRole', null)
            ->assertJsonPath('data.permissions', []);
        foreach (['/api/v1/admin/dashboard', '/api/v1/admin/leads', '/api/v1/admin/analytics',
            '/api/v1/admin/analytics/finance', '/api/v1/admin/expenses/statistics', '/api/v1/admin/employees/stats'] as $uri) {
            $this->getJson($uri)->assertForbidden();
        }

        // The business's own owner login works as before.
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'owner')->create());
        $this->getJson('/api/v1/admin/leads')->assertOk()->assertJsonPath('data.0.name', 'Shop client');
    }

    public function test_the_business_page_has_no_revenue_or_expense_figures(): void
    {
        $this->staffInShop();

        $data = $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/overview")->assertOk()->json('data');

        $this->assertSame(
            ['tenant', 'activity', 'subscription', 'payments', 'modules', 'agents', 'assistant', 'people', 'support', 'sites'],
            array_keys($data),
        );
        foreach (['revenue', 'profit', 'expenses', 'finance', 'sales', 'orders', 'leads', 'income'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase('"'.$word, json_encode($data));
        }
    }

    public function test_the_business_page_on_the_platform_level_has_no_business_data(): void
    {
        $owner = User::factory()->forTenant($this->shop, 'owner')->create();
        User::factory()->forTenant($this->shop, 'moderator')->create();
        app(Agents::class)->setActive($this->shop, AgentCatalog::find('analyst'), true);
        app(Agents::class)->setNotes($this->shop, AgentCatalog::find('analyst'), 'Secret plan of the business');

        // The owner works today: a day of activity.
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/me')->assertOk();

        $this->staffInShop();
        $response = $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', 'test-shop')
            ->assertJsonPath('data.activity.activeDays', 1)
            ->assertJsonPath('data.activity.activeDaysRecent', 1)
            ->assertJsonPath('data.subscription.status', 'trial')
            ->assertJsonPath('data.people.logins', 2)
            ->assertJsonPath('data.people.byRole.owner', 1)
            ->assertJsonPath('data.people.byRole.moderator', 1)
            ->assertJsonPath('data.payments.count', 0)
            ->assertJsonPath('data.assistant.used', 0)
            ->assertJsonCount(30, 'data.activity.recent');

        $agents = collect($response->json('data.agents'));
        $this->assertTrue($agents->firstWhere('key', 'analyst')['active']);
        $this->assertCount(count(AgentCatalog::all()), $agents);

        $body = $response->getContent();
        $this->assertStringNotContainsString('Shop client', $body);
        $this->assertStringNotContainsString('Secret plan', $body);
        $this->assertStringNotContainsString('notes', $body);
    }

    public function test_the_platform_agents_list_leaves_out_what_agents_remember(): void
    {
        app(Agents::class)->setNotes($this->shop, AgentCatalog::find('analyst'), 'Secret plan of the business');
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $body = $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/agents")->assertOk()->getContent();

        $this->assertStringNotContainsString('Secret plan', $body);
    }

    public function test_payments_on_the_business_page_are_for_the_super_admin_only(): void
    {
        $this->staffInShop('platformModerator');

        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/overview")
            ->assertOk()
            ->assertJsonPath('data.payments', null)
            ->assertJsonPath('data.assistant.bonuses', null);
    }

    public function test_the_business_people_cannot_open_the_platform_page(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'owner')->create());

        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/overview")->assertForbidden();
    }

    public function test_staff_requests_are_not_counted_as_the_business_activity(): void
    {
        $this->staffInShop();
        $this->getJson('/api/v1/admin/me')->assertOk();

        $this->assertSame(0, DB::table('business_activity_days')->where('tenant_id', $this->shop->id)->count());
    }
}
