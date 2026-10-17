<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceSlugRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());

        $this->category = ServiceCategory::create([
            'slug' => 'auto-care',
            'sort_order' => 3,
            'name' => ['hy' => 'Ավտոխնամք', 'ru' => 'Автоуход', 'en' => 'Auto Care'],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'slug' => 'car-interior-cleaning',
            'status' => 'draft',
            'name' => ['hy' => 'Սրահի քիմմաքրում', 'ru' => 'Химчистка салона', 'en' => 'Interior Cleaning'],
        ], $overrides);
    }

    public function test_index_lists_services_of_every_status(): void
    {
        Service::create($this->payload());
        Service::create($this->payload(['slug' => 'trunk-cleaning', 'status' => 'archived']));

        $this->getJson('/api/v1/admin/services')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_creates_a_draft_service(): void
    {
        $response = $this->postJson('/api/v1/admin/services', $this->payload());

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('services', ['slug' => 'car-interior-cleaning', 'status' => 'draft']);
    }

    public function test_store_rejects_an_invalid_status(): void
    {
        $this->postJson('/api/v1/admin/services', $this->payload(['status' => 'not-a-status']))
            ->assertStatus(422);
    }

    public function test_store_rejects_publishing_without_description_in_all_locales(): void
    {
        $this->postJson('/api/v1/admin/services', $this->payload([
            'status' => 'active',
            'description' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => ''],
        ]))->assertStatus(422);
    }

    public function test_update_can_change_status_to_active(): void
    {
        $service = Service::create($this->payload());

        $this->patchJson("/api/v1/admin/services/{$service->id}", [
            'status' => 'active',
            'description' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'D'],
            'short_description' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'D'],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_update_changing_slug_records_a_redirect(): void
    {
        $service = Service::create($this->payload());

        $this->patchJson("/api/v1/admin/services/{$service->id}", ['slug' => 'new-slug'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-slug');

        $this->assertDatabaseHas('service_slug_redirects', [
            'old_slug' => 'car-interior-cleaning',
            'service_id' => $service->id,
        ]);
    }

    public function test_update_does_not_duplicate_redirect_rows_on_repeated_saves(): void
    {
        $service = Service::create($this->payload());

        $this->patchJson("/api/v1/admin/services/{$service->id}", ['slug' => 'new-slug'])->assertOk();
        $this->patchJson("/api/v1/admin/services/{$service->id}", ['sort_order' => 5])->assertOk();

        $this->assertEquals(
            1,
            ServiceSlugRedirect::query()->where('old_slug', 'car-interior-cleaning')->count(),
        );
    }

    public function test_destroy_removes_the_service(): void
    {
        $service = Service::create($this->payload());

        $this->deleteJson("/api/v1/admin/services/{$service->id}")->assertNoContent();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
