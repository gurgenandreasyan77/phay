<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-10-17 12:00:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function signInTo(Tenant $tenant): void
    {
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        Sanctum::actingAs($user);
    }

    private function lead(array $attributes = [], ?string $createdAt = null, ?string $completedAt = null): Lead
    {
        $lead = Lead::create(array_merge([
            'name' => 'Client',
            'phone' => '+37491000000',
            'preferred_locale' => 'hy',
            'consent' => true,
            'source' => 'website',
        ], $attributes));

        $lead->forceFill(array_filter([
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
        ]))->saveQuietly();

        return $lead;
    }

    public function test_completing_an_order_records_when_it_was_completed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $lead = $this->lead();

        $this->patchJson("/api/v1/admin/leads/{$lead->id}", ['status' => 'completed', 'final_price' => 15000])
            ->assertOk()
            ->assertJsonPath('data.finalPrice', 15000)
            ->assertJsonPath('data.completedAt', Date::now()->toIso8601String());

        $this->patchJson("/api/v1/admin/leads/{$lead->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.completedAt', null);
    }

    public function test_it_summarises_orders_and_revenue_of_the_period(): void
    {
        $shop = $this->createTenant('test-shop');
        $this->signInTo($shop);

        $this->asTenant($shop, function () {
            $sofa = $this->createService('sofa', 'Բազմոց');
            $this->lead(['service_id' => $sofa->id, 'status' => LeadStatus::Completed, 'final_price' => 10000], '2026-10-10 10:00:00', '2026-10-11 10:00:00');
            $this->lead(['service_id' => $sofa->id, 'status' => LeadStatus::Completed, 'final_price' => 20000, 'utm_source' => 'instagram'], '2026-10-15 10:00:00', '2026-10-16 10:00:00');
            $this->lead(['status' => LeadStatus::Completed], '2026-10-16 10:00:00', '2026-10-16 12:00:00');
            $this->lead(['status' => LeadStatus::Cancelled], '2026-10-16 10:00:00');
            $this->lead([], '2026-10-17 09:00:00');
            // Previous 30 days.
            $this->lead(['status' => LeadStatus::Completed, 'final_price' => 5000], '2026-09-01 10:00:00', '2026-09-02 10:00:00');
        });

        // Another business's orders never show up.
        $this->lead(['status' => LeadStatus::Completed, 'final_price' => 999999], '2026-10-16 10:00:00', '2026-10-16 11:00:00');

        $response = $this->getJson('/api/v1/admin/analytics?period=30')->assertOk();

        $response->assertJsonPath('data.totals.orders', 5)
            ->assertJsonPath('data.totals.completed', 3)
            ->assertJsonPath('data.totals.cancelled', 1)
            ->assertJsonPath('data.totals.revenue', 30000)
            ->assertJsonPath('data.totals.averageCheck', 15000)
            ->assertJsonPath('data.totals.completedWithoutPrice', 1)
            ->assertJsonPath('data.totals.conversion', 60)
            ->assertJsonPath('data.previous.revenue', 5000)
            ->assertJsonPath('data.topServices.0.name', 'Բազմոց')
            ->assertJsonPath('data.topServices.0.revenue', 30000)
            ->assertJsonPath('data.openOrders', 1)
            ->assertJsonCount(30, 'data.series');

        $sources = collect($response->json('data.sources'))->pluck('orders', 'source');
        $this->assertSame(['website' => 4, 'instagram' => 1], $sources->all());

        $day = collect($response->json('data.series'))->firstWhere('date', '2026-10-16');
        $this->assertSame(['date' => '2026-10-16', 'orders' => 2, 'revenue' => 20000], $day);
    }

    public function test_a_year_is_grouped_by_month_and_unknown_periods_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/analytics?period=365')->assertOk()->assertJsonCount(13, 'data.series');
        $this->getJson('/api/v1/admin/analytics?period=12')->assertUnprocessable();
    }
}
