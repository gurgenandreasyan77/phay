<?php

namespace Tests\Feature\Tenancy;

use App\Features\FeatureFlag;
use App\Models\Lead;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

/**
 * Public API: the X-Site-Key header decides whose content is served and
 * who receives the leads.
 */
class SiteKeyTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $main;

    private Tenant $shop;

    private string $shopKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Tenant::default();
        $this->shop = $this->withServices($this->createTenant('test-shop'));
        $this->shopKey = $this->createSite($this->shop)->public_key;

        $this->createService('sofa-cleaning', 'Main sofa');
        $this->asTenant($this->shop, fn () => $this->createService('sofa-cleaning', 'Shop sofa'));
    }

    public function test_the_default_business_site_key_serves_the_default_business(): void
    {
        // Tests send the default business's site key unless they pass another.
        $this->getJson('/api/v1/services?lang=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Main sofa');
    }

    public function test_without_a_key_nothing_is_served(): void
    {
        $this->withoutHeader('X-Site-Key');

        $this->getJson('/api/v1/services?lang=en')->assertUnauthorized();
        $this->getJson('/api/v1/settings')->assertUnauthorized();
        $this->postJson('/api/v1/leads', ['name' => 'Ani', 'phone' => '+37455000000', 'consent' => true])->assertUnauthorized();
    }

    public function test_with_a_key_only_that_business_is_served(): void
    {
        $this->getJson('/api/v1/services?lang=en', ['X-Site-Key' => $this->shopKey])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Shop sofa');
    }

    public function test_the_same_slug_resolves_per_business(): void
    {
        $this->getJson('/api/v1/services/sofa-cleaning?lang=en')
            ->assertOk()
            ->assertJsonPath('data.name', 'Main sofa');

        $this->getJson('/api/v1/services/sofa-cleaning?lang=en', ['X-Site-Key' => $this->shopKey])
            ->assertOk()
            ->assertJsonPath('data.name', 'Shop sofa');
    }

    public function test_a_new_business_starts_empty(): void
    {
        $empty = $this->createTenant('empty-shop');
        $key = $this->createSite($empty)->public_key;

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key])->assertNotFound();
        $this->withServices($empty);

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key])->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/categories', ['X-Site-Key' => $key])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->getJson('/api/v1/services', ['X-Site-Key' => 'site_pk_does-not-exist'])
            ->assertUnauthorized();
    }

    public function test_a_deactivated_site_key_is_rejected(): void
    {
        $site = $this->createSite($this->shop, ['is_active' => false]);

        $this->getJson('/api/v1/services', ['X-Site-Key' => $site->public_key])
            ->assertUnauthorized();
    }

    public function test_a_suspended_business_is_not_served(): void
    {
        $this->shop->update(['status' => Tenant::STATUS_SUSPENDED]);

        $this->getJson('/api/v1/services', ['X-Site-Key' => $this->shopKey])
            ->assertForbidden();
    }

    public function test_allowed_origins_are_enforced_for_browser_requests(): void
    {
        $site = $this->createSite($this->shop, ['allowed_origins' => ['https://shop.am']]);

        $this->getJson('/api/v1/services', ['X-Site-Key' => $site->public_key, 'Origin' => 'https://evil.example'])
            ->assertForbidden();

        $this->getJson('/api/v1/services', ['X-Site-Key' => $site->public_key, 'Origin' => 'https://shop.am'])
            ->assertOk();

        // Server-side callers (the Next.js site) send no Origin header.
        $this->getJson('/api/v1/services', ['X-Site-Key' => $site->public_key])
            ->assertOk();
    }

    public function test_a_lead_sent_with_a_key_goes_to_that_business_only(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Shop client',
            'phone' => '+374 91 123456',
            'service_slug' => 'sofa-cleaning',
            'preferred_locale' => 'hy',
            'consent' => true,
        ], ['X-Site-Key' => $this->shopKey])->assertCreated();

        $lead = Lead::withoutTenancy()->sole();

        $this->assertSame($this->shop->id, $lead->tenant_id);
        $this->assertSame(
            $this->asTenant($this->shop, fn () => Service::query()->sole()->id),
            $lead->service_id,
        );
        $this->assertSame(0, Lead::count(), 'The default business must not see the lead.');
    }

    public function test_settings_and_feature_flags_are_per_business(): void
    {
        SiteSetting::current()->update(['address' => ['hy' => 'Երևան', 'ru' => 'Ереван', 'en' => 'Yerevan']]);
        $this->asTenant($this->shop, function () {
            SiteSetting::current()->update(['address' => ['hy' => 'Գյումրի', 'ru' => 'Гюмри', 'en' => 'Gyumri']]);
            Feature::deactivate(FeatureFlag::Market->value);
        });

        $this->getJson('/api/v1/settings?lang=en')
            ->assertOk()
            ->assertJsonPath('data.address', 'Yerevan')
            ->assertJsonPath('data.features.market', true);

        $this->getJson('/api/v1/settings?lang=en', ['X-Site-Key' => $this->shopKey])
            ->assertOk()
            ->assertJsonPath('data.address', 'Gyumri')
            ->assertJsonPath('data.features.market', false);

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products', ['X-Site-Key' => $this->shopKey])->assertNotFound();
    }

    public function test_the_request_tenant_does_not_leak_into_later_code(): void
    {
        $this->getJson('/api/v1/services', ['X-Site-Key' => $this->shopKey])->assertOk();

        $this->assertSame($this->main->id, app(TenantContext::class)->id());
    }
}
