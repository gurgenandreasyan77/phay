<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class RequireGlobalFeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/auto-care-gated', fn () => response()->json(['data' => 'ok']))
            ->middleware('feature.global:auto-care');
    }

    public function test_passes_through_when_the_flag_is_active(): void
    {
        $this->getJson('/__test/auto-care-gated')->assertOk()->assertJsonPath('data', 'ok');
    }

    public function test_returns_404_when_the_flag_is_inactive(): void
    {
        Feature::globally()->deactivate(FeatureFlag::AutoCare->value);

        $this->getJson('/__test/auto-care-gated')->assertNotFound();
    }
}
