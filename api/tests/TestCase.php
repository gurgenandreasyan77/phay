<?php

namespace Tests;

use App\Http\Middleware\ResolveSiteTenant;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The public API needs a site key. Requests act as the default
        // business's site unless a test sends another key.
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $key = Site::query()->where('tenant_id', Tenant::default()->getKey())->value('public_key');

            if ($key) {
                $this->withHeader(ResolveSiteTenant::HEADER, $key);
            }
        }
    }
}
