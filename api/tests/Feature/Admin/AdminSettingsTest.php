<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_show_returns_the_singleton_settings_row(): void
    {
        $response = $this->getJson('/api/v1/admin/settings')->assertOk();

        $response->assertJsonStructure(['data' => ['address', 'channels']]);
    }

    public function test_update_persists_the_address_and_returns_it(): void
    {
        $response = $this->putJson('/api/v1/admin/settings', [
            'address' => [
                'hy' => 'Երևան, Կոմիտաս 1',
                'ru' => 'Ереван, Комитас 1',
                'en' => 'Yerevan, Komitas 1',
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.address.en', 'Yerevan, Komitas 1');

        $this->assertDatabaseHas('site_settings', ['id' => 1]);
    }
}
