<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_fields_are_enforced(): void
    {
        $this->postJson('/api/v1/leads', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'preferred_locale', 'consent']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Test',
            'phone' => 'not-a-phone',
            'preferred_locale' => 'hy',
            'consent' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_consent_must_be_accepted(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Test',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['consent']);
    }

    public function test_preferred_date_cannot_be_in_the_past(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Test',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => true,
            'preferred_date' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_date']);
    }

    public function test_an_unknown_service_slug_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', [
            'name' => 'Test',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => true,
            'service_slug' => 'does-not-exist',
        ])->assertStatus(422)->assertJsonValidationErrors(['service_slug']);
    }

    public function test_validation_errors_are_localized(): void
    {
        $response = $this->postJson('/api/v1/leads?lang=hy', []);

        $response->assertStatus(422);
        $this->assertStringContainsString('պարտադիր է', $response->json('errors.name.0'));
    }
}
