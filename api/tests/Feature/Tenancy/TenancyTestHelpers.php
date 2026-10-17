<?php

namespace Tests\Feature\Tenancy;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Site;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

trait TenancyTestHelpers
{
    protected function createTenant(string $slug = 'test-shop', string $status = Tenant::STATUS_ACTIVE): Tenant
    {
        return Tenant::create([
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'default_locale' => 'hy',
            'locales' => ['hy', 'ru', 'en'],
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
            'status' => $status,
        ]);
    }

    protected function createSite(Tenant $tenant, array $attributes = []): Site
    {
        return Site::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $tenant->slug.'.am',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Runs the callback as the given business, so rows it creates belong to it.
     */
    protected function asTenant(Tenant $tenant, callable $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }

    protected function createService(string $slug, string $name, string $categorySlug = 'cleaning'): Service
    {
        $category = ServiceCategory::query()->firstOrCreate(['slug' => $categorySlug], [
            'sort_order' => 1,
            'name' => ['hy' => $categorySlug, 'ru' => $categorySlug, 'en' => $categorySlug],
        ]);

        return Service::create([
            'category_id' => $category->id,
            'slug' => $slug,
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => $name, 'ru' => $name, 'en' => $name],
            'short_description' => ['hy' => 'Կարճ', 'ru' => 'Кратко', 'en' => 'Short'],
            'description' => ['hy' => 'Ամբողջական', 'ru' => 'Полное', 'en' => 'Full'],
        ]);
    }
}
