<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use App\Models\ContactChannel;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_exposes_the_modules_and_whether_there_is_car_care(): void
    {
        $this->getJson('/api/v1/settings?lang=en')
            ->assertOk()
            ->assertJsonPath('data.features.services', true)
            ->assertJsonPath('data.features.market', true)
            ->assertJsonPath('data.features.site', true)
            ->assertJsonPath('data.features.autoCare', false)
            ->assertJsonMissingPath('data.features.orders')
            ->assertJsonMissingPath('data.features.dryCleaning');

        // Car care is a category of the services catalog.
        ServiceCategory::create(['slug' => 'auto-care', 'sort_order' => 1, 'name' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A']]);
        $this->getJson('/api/v1/settings?lang=en')->assertJsonPath('data.features.autoCare', true);

        Feature::deactivate(FeatureFlag::Services->value);
        $this->getJson('/api/v1/settings?lang=en')->assertJsonPath('data.features.autoCare', false);
    }

    public function test_settings_returns_the_localized_address_and_active_channels(): void
    {
        SiteSetting::current()->update([
            'address' => ['hy' => 'Հասցե', 'ru' => 'Адрес', 'en' => 'Address'],
        ]);

        ContactChannel::create([
            'type' => 'phone', 'value' => '+374 55 337633', 'is_primary' => true, 'sort_order' => 1,
        ]);
        ContactChannel::create([
            'type' => 'whatsapp', 'value' => '+37477796420', 'sort_order' => 2, 'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/settings?lang=en')->assertOk();

        $response->assertJsonPath('data.address', 'Address');
        $this->assertCount(1, $response->json('data.channels'));
        $response->assertJsonPath('data.channels.0.type', 'phone');
        $response->assertJsonPath('data.channels.0.isPrimary', true);
    }
}
