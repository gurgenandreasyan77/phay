<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class RequireFeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/market-gated', fn () => response()->json(['data' => 'ok']))
            ->middleware('feature:market');
    }

    public function test_passes_through_when_the_flag_is_active(): void
    {
        $this->getJson('/__test/market-gated')->assertOk()->assertJsonPath('data', 'ok');
    }

    public function test_returns_404_when_the_flag_is_inactive(): void
    {
        Feature::deactivate(FeatureFlag::Market->value);

        $this->getJson('/__test/market-gated')->assertNotFound();
    }
}
