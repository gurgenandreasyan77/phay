<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_submissions_are_throttled(): void
    {
        $limit = (int) config('services.leads.rate_limit_per_minute');
        $payload = [
            'name' => 'Test',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => true,
        ];

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/leads', $payload)->assertCreated();
        }

        $this->postJson('/api/v1/leads', $payload)->assertStatus(429);
    }
}
