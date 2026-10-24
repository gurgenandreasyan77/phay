<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_usable_against_me(): void
    {
        User::factory()->create([
            'email' => 'admin@phay.am',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@phay.am',
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email']]]);

        $token = $response->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@phay.am');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@phay.am',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@phay.am',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/leads')->assertStatus(401);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/admin/me')->assertStatus(401);
    }

    public function test_repeated_bad_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'admin@phay.am', 'password' => Hash::make('correct-password')]);

        $limit = (int) config('services.admin_login.rate_limit_per_minute');
        $payload = ['email' => 'admin@phay.am', 'password' => 'wrong-password'];

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/admin/login', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/admin/login', $payload)->assertStatus(429);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $token = $user->createToken('admin-panel');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson('/api/v1/admin/logout')
            ->assertOk();

        // Asserted against the database rather than a follow-up authenticated
        // request: within a single test, Laravel's testing app instance
        // reuses the same cached "sanctum" guard across calls, so a second
        // request would see the already-resolved user instead of
        // re-validating the (now deleted) token.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
