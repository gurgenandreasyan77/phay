<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/** The search in the admin's header, by role and module. */
class HeaderSearchTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->createTenant('test-salon');
        $this->withServices($this->salon);
        Sanctum::actingAs(User::factory()->forTenant($this->salon)->create());

        foreach (['orders' => 'Sofa orders', 'expenses' => 'Sofa supplies'] as $section => $name) {
            $table = $this->postJson('/api/v1/admin/data-tables', [
                'section' => $section,
                'name' => $name,
                'columns' => [['label' => 'Item', 'type' => 'text', 'role' => 'item'], ['label' => 'Sum', 'type' => 'money', 'role' => 'amount']],
            ])->assertCreated()->json('data');
            [$item, $sum] = array_column($table['columns'], 'key');
            $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$item => "Corner sofa ({$section})", $sum => 9900]])->assertCreated();
        }

        $this->postJson('/api/v1/admin/employees', ['name' => 'Sofia Petrosyan', 'position' => 'Master'])->assertCreated();
        $this->asTenant($this->salon, function () {
            Lead::create(['name' => 'Sofi', 'phone' => '+374 55 33 76 33', 'preferred_locale' => 'hy', 'consent' => true, 'source' => 'website']);
            $this->createService('sofa-cleaning', 'Sofa cleaning');
        });
    }

    /** @return array<string, list<array{title: string, url: string}>> */
    private function search(string $q): array
    {
        return collect($this->getJson('/api/v1/admin/search?q='.urlencode($q))->assertOk()->json('data.groups'))
            ->mapWithKeys(fn (array $group) => [$group['key'] => $group['items']])
            ->all();
    }

    public function test_the_owner_finds_everything_of_the_business(): void
    {
        $found = $this->search('SOF');

        $this->assertEqualsCanonicalizing(['tables', 'rows', 'leads', 'employees', 'services'], array_keys($found));
        $this->assertCount(2, $found['tables']);
        $this->assertCount(2, $found['rows']);
        $this->assertStringContainsString('Corner sofa', $found['rows'][0]['title']);
        $this->assertStringContainsString('/tables/', $found['rows'][0]['url']);
        $this->assertStringContainsString('?search=SOF', $found['rows'][0]['url']);
        $this->assertSame('Sofi', $found['leads'][0]['title']);
        $this->assertSame('Sofia Petrosyan', $found['employees'][0]['title']);
        $this->assertSame('/admin/services', $found['services'][0]['url']);

        // A phone is found however it is written.
        $this->assertSame('Sofi', $this->search('055337633')['leads'][0]['title'] ?? null);

        $this->getJson('/api/v1/admin/search?q=s')->assertUnprocessable();
        $this->assertSame([], $this->search('nothing like it'));
    }

    public function test_a_moderator_finds_only_orders(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());

        $found = $this->search('sof');

        $this->assertEqualsCanonicalizing(['tables', 'rows', 'leads'], array_keys($found));
        $this->assertSame(['Sofa orders'], array_column($found['tables'], 'title'));
        $this->assertCount(1, $found['rows']);
    }

    public function test_modules_that_are_off_are_not_searched(): void
    {
        Feature::for($this->salon)->deactivate('orders');
        Feature::for($this->salon)->deactivate('services');

        $found = $this->search('sof');

        $this->assertEqualsCanonicalizing(['tables', 'rows', 'employees'], array_keys($found));
        $this->assertSame(['Sofa supplies'], array_column($found['tables'], 'title'));
    }

    public function test_platform_staff_also_find_businesses(): void
    {
        $this->createTenant('sofa-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->assertSame(['Sofa Shop'], array_column($this->search('sofa sh')['businesses'], 'title'));

        Sanctum::actingAs(User::factory()->forTenant($this->salon)->create());
        $this->assertArrayNotHasKey('businesses', $this->search('sofa sh'));
    }
}
