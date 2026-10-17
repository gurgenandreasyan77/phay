<?php

namespace Tests\Feature\Admin;

use App\Models\ContactChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContactChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_store_creates_a_channel(): void
    {
        $response = $this->postJson('/api/v1/admin/contact-channels', [
            'type' => 'whatsapp',
            'value' => '+374 55 337633',
            'label' => 'Sales',
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'whatsapp');
        $this->assertDatabaseHas('contact_channels', ['type' => 'whatsapp', 'label' => 'Sales']);
    }

    public function test_store_rejects_an_invalid_type(): void
    {
        $this->postJson('/api/v1/admin/contact-channels', [
            'type' => 'not-a-real-type',
            'value' => 'whatever',
        ])->assertStatus(422);
    }

    public function test_update_changes_fields(): void
    {
        $channel = ContactChannel::create(['type' => 'phone', 'value' => '+37477796420']);

        $response = $this->patchJson("/api/v1/admin/contact-channels/{$channel->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.isActive', false);
        $this->assertDatabaseHas('contact_channels', ['id' => $channel->id, 'is_active' => false]);
    }

    public function test_destroy_removes_the_channel(): void
    {
        $channel = ContactChannel::create(['type' => 'phone', 'value' => '+37477796420']);

        $this->deleteJson("/api/v1/admin/contact-channels/{$channel->id}")->assertNoContent();

        $this->assertDatabaseMissing('contact_channels', ['id' => $channel->id]);
    }
}
