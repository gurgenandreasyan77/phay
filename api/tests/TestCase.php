<?php

namespace Tests;

use App\Http\Middleware\ResolveSiteTenant;
use App\Media\DocumentConverter;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeDocumentConverter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never the real media service (the dev container has MEDIA_URL): old
        // office files are refused and PDFs go whole, unless a test fakes it.
        $converter = new FakeDocumentConverter;
        $converter->configured = false;
        $this->app->instance(DocumentConverter::class, $converter);

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
