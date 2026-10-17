<?php

namespace Tests\Feature\Admin;

use App\Models\CalculatorSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCalculatorSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(): array
    {
        return [
            'cleaning_rates' => [
                'apartment' => ['regular' => 400, 'general' => 650, 'postConstruction' => 1000],
                'house' => ['regular' => 450, 'general' => 750, 'postConstruction' => 1100],
                'office' => ['regular' => 350, 'general' => 550, 'postConstruction' => 900],
            ],
            'room_rates' => [
                'studio' => ['regular' => 9500, 'general' => 14500, 'postConstruction' => 22500],
                'oneRoom' => ['regular' => 14500, 'general' => 20500, 'postConstruction' => 32500],
                'twoRoom' => ['regular' => 25500, 'general' => 34500, 'postConstruction' => 52500],
                'threeRoom' => ['regular' => 34500, 'general' => 46500, 'postConstruction' => 70500],
                'fourPlusRoom' => ['regular' => 45500, 'general' => 60500, 'postConstruction' => 90500],
            ],
            'cleaning_discounts' => [
                'regular' => 0,
                'general' => 15,
                'postConstruction' => 0,
            ],
            'bathroom_rate' => 3000,
            'vitrage_rate' => 550,
            'extra_floor_rate' => 5500,
            'heavy_soiling_multiplier' => 0.2,
            'cleaning_minimum' => 27000,
            'dry_cleaning_rates' => [
                'sofa2' => 16000,
                'sofa3' => 21000,
                'sofaCorner' => 29000,
                'armchair' => 8500,
                'chair' => 3200,
                'mattressSingle' => 12500,
                'mattressDouble' => 18500,
                'carpet' => 1300,
                'steam' => 5500,
            ],
        ];
    }

    public function test_show_returns_default_settings(): void
    {
        $this->getJson('/api/v1/admin/calculator-settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['cleaningRates', 'cleaningDiscounts', 'bathroomRate', 'dryCleaningRates']]);
    }

    public function test_update_changes_rates(): void
    {
        $response = $this->putJson('/api/v1/admin/calculator-settings', $this->payload());

        $response->assertOk()
            ->assertJsonPath('data.bathroomRate', 3000)
            ->assertJsonPath('data.cleaningDiscounts.general', 15);
        $this->assertDatabaseHas('calculator_settings', ['id' => CalculatorSetting::current()->id, 'bathroom_rate' => 3000]);
    }

    public function test_update_rejects_missing_fields(): void
    {
        $payload = $this->payload();
        unset($payload['bathroom_rate']);

        $this->putJson('/api/v1/admin/calculator-settings', $payload)->assertStatus(422);
    }
}
