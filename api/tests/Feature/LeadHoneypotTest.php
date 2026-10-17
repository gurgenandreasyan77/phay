<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadHoneypotTest extends TestCase
{
    use RefreshDatabase;

    public function test_filling_the_honeypot_field_is_silently_dropped(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Bot',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => true,
            'website' => 'http://spam.example',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('leads', 0);
    }
}
