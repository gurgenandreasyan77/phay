<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Every API error answers the same JSON: message, code, status, request id
 * (and errors / retry_after where they apply); file, line and trace only
 * with APP_DEBUG.
 */
class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/_test')->group(function () {
            Route::get('boom', fn () => throw new RuntimeException('Database exploded at row 7'));
            Route::get('forbidden', fn () => abort(403, 'Your role in this business does not allow this.'));
            Route::get('policy', fn () => throw new AuthorizationException('Only the owner can do this.'));
            Route::get('model', fn () => throw (new ModelNotFoundException)->setModel(User::class, [42]));
            Route::post('validate', fn () => request()->validate(['name' => ['required', 'string']]));
            Route::get('throttled', fn () => 'ok')->middleware('throttle:1,1');
            Route::get('private', fn () => 'ok')->middleware('auth:sanctum');
        });
    }

    public function test_server_error_hides_details_outside_debug(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/boom');

        $response->assertStatus(500)
            ->assertJson(['message' => 'Server Error', 'code' => 'server_error', 'status' => 500])
            ->assertJsonMissingPath('debug');
        $this->assertStringNotContainsString('exploded', $response->getContent());
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('request_id'));
        $this->assertNotEmpty($response->json('request_id'));
    }

    public function test_server_error_shows_class_file_and_trace_in_debug(): void
    {
        config(['app.debug' => true]);

        $this->getJson('/api/v1/_test/boom')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Database exploded at row 7')
            ->assertJsonPath('code', 'server_error')
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonPath('debug.message', 'Database exploded at row 7')
            ->assertJsonStructure(['debug' => ['file', 'line', 'trace']]);
    }

    public function test_request_id_from_the_admin_is_kept(): void
    {
        $this->withHeader('X-Request-Id', 'bff-1234abcd')
            ->getJson('/api/v1/_test/forbidden')
            ->assertHeader('X-Request-Id', 'bff-1234abcd')
            ->assertJsonPath('request_id', 'bff-1234abcd');
    }

    public function test_a_malformed_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-Id', "bad id\nwith newline")->getJson('/api/v1/_test/forbidden');

        $this->assertNotSame("bad id\nwith newline", $response->json('request_id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->json('request_id'));
    }

    public function test_successful_answers_carry_the_request_id_header(): void
    {
        $this->getJson('/api/health')->assertHeader('X-Request-Id');
    }

    public function test_unauthenticated(): void
    {
        $this->getJson('/api/v1/_test/private')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated', 'status' => 401]);
    }

    public function test_forbidden_keeps_the_reason(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/_test/forbidden')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Your role in this business does not allow this.',
                'code' => 'forbidden',
                'status' => 403,
            ]);

        $this->getJson('/api/v1/_test/policy')
            ->assertStatus(403)
            ->assertJson(['message' => 'Only the owner can do this.', 'code' => 'forbidden']);
    }

    public function test_missing_model_does_not_name_it_outside_debug(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/model');

        $response->assertStatus(404)->assertJson(['message' => 'Not found.', 'code' => 'not_found']);
        $this->assertStringNotContainsString('User', $response->getContent());

        config(['app.debug' => true]);

        $this->getJson('/api/v1/_test/model')
            ->assertStatus(404)
            ->assertJsonPath('debug.exception', ModelNotFoundException::class)
            ->assertJsonPath('debug.message', 'No query results for model [App\Models\User] 42');
    }

    public function test_unknown_route(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/v1/no-such-thing')
            ->assertStatus(404)
            ->assertJson(['message' => 'Not Found', 'code' => 'not_found', 'status' => 404]);
    }

    public function test_wrong_method(): void
    {
        $this->deleteJson('/api/v1/_test/boom')
            ->assertStatus(405)
            ->assertJsonPath('code', 'method_not_allowed');
    }

    public function test_validation_keeps_laravel_errors(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('status', 422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_too_many_requests_says_when_to_retry(): void
    {
        $this->getJson('/api/v1/_test/throttled')->assertOk();

        $this->getJson('/api/v1/_test/throttled')
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertJsonPath('status', 429)
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['retry_after']);
    }
}
