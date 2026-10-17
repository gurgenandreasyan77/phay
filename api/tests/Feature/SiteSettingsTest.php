<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_exposes_the_auto_care_feature_flag_status(): void
    {
        $this->getJson('/api/v1/settings?lang=en')
            ->assertOk()
            ->assertJsonPath('data.features.autoCare', true);

        Feature::deactivate(FeatureFlag::AutoCare->value);

        $this->getJson('/api/v1/settings?lang=en')
            ->assertOk()
            ->assertJsonPath('data.features.autoCare', false);
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
