<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use Database\Seeders\AutoCarePostSeeder;
use Database\Seeders\AutoCareServiceSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServicePackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCareSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function runAll(): void
    {
        (new ServiceCategorySeeder)->run();
        (new AutoCareServiceSeeder)->run();
        (new ServicePackageSeeder)->run();
        (new AutoCarePostSeeder)->run();
    }

    public function test_running_auto_care_seeders_twice_does_not_create_duplicates(): void
    {
        $this->runAll();

        $categoryCount = ServiceCategory::count();
        $serviceCount = Service::count();
        $packageCount = ServicePackage::count();
        $postCount = Post::where('category', 'auto-care')->count();

        $this->assertGreaterThan(0, $serviceCount);
        $this->assertGreaterThan(0, $packageCount);
        $this->assertGreaterThan(0, $postCount);

        $this->runAll();

        $this->assertSame($categoryCount, ServiceCategory::count());
        $this->assertSame($serviceCount, Service::count());
        $this->assertSame($packageCount, ServicePackage::count());
        $this->assertSame($postCount, Post::where('category', 'auto-care')->count());
    }

    public function test_all_seeded_auto_care_services_are_active_with_no_price(): void
    {
        $this->runAll();

        $autoCareServices = Service::query()
            ->whereHas('category', fn ($q) => $q->where('slug', 'auto-care'))
            ->get();

        $this->assertGreaterThan(0, $autoCareServices->count());
        $this->assertSame($autoCareServices->count(), $autoCareServices->where('status', 'active')->count());
        $this->assertSame(0, $autoCareServices->whereNotNull('price_amount')->count());

        $this->assertSame(0, ServicePackage::where('status', 'active')->count());
    }

    public function test_future_exterior_detailing_services_are_not_seeded(): void
    {
        $this->runAll();

        // Servio only offers interior cleaning/detailing — these exterior/body
        // services were removed from the seeder and must never reappear.
        $futureSlugs = [
            'body-polishing', 'body-coating', 'headlight-restoration', 'wheel-detailing',
            'engine-bay-detailing', 'rain-repellent', 'leather-coating', 'fabric-coating',
            'full-detailing-package',
        ];

        $this->assertSame(0, Service::whereIn('slug', $futureSlugs)->count());
    }

    public function test_all_auto_care_articles_are_seeded_as_draft(): void
    {
        $this->runAll();

        $this->assertSame(0, Post::where('category', 'auto-care')->where('status', '!=', 'draft')->count());
    }
}
