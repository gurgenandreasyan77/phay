<?php

namespace Tests\Feature\Tenancy;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_creates_the_default_business_and_its_site(): void
    {
        $servio = Tenant::default();

        $this->assertSame('servio', $servio->slug);
        $this->assertStringStartsWith(Site::KEY_PREFIX, $servio->sites()->sole()->public_key);
    }

    public function test_a_business_and_a_site_can_be_created_from_the_console(): void
    {
        $owner = User::factory()->withoutTenant()->create(['email' => 'owner@shop.am']);

        $this->artisan('platform:tenant:create', ['name' => 'Test Shop', '--owner' => 'owner@shop.am'])
            ->expectsOutputToContain('Created business')
            ->assertSuccessful();

        $tenant = Tenant::query()->where('slug', 'test-shop')->sole();
        $this->assertTrue($owner->tenants()->whereKey($tenant->id)->exists());

        $this->artisan('platform:site:create', ['tenant' => 'test-shop', 'name' => 'shop.am', '--origin' => ['https://shop.am']])
            ->expectsOutputToContain('Site key: site_pk_')
            ->assertSuccessful();

        $site = $tenant->sites()->sole();
        $this->assertSame(['https://shop.am'], $site->allowed_origins);

        $this->getJson('/api/v1/services', ['X-Site-Key' => $site->public_key])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_duplicate_slugs_and_unknown_businesses_are_refused(): void
    {
        $this->artisan('platform:tenant:create', ['name' => 'Servio'])->assertFailed();
        $this->artisan('platform:site:create', ['tenant' => 'nope', 'name' => 'x'])->assertFailed();
        $this->artisan('platform:tenant:create', ['name' => 'Shop', '--owner' => 'missing@shop.am'])->assertFailed();
    }
}
