<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceVideoTest extends TestCase
{
    use RefreshDatabase;

    private function seedService(): Service
    {
        $category = ServiceCategory::create([
            'slug' => 'auto-care',
            'sort_order' => 3,
            'name' => ['hy' => 'Ավտոխնամք', 'ru' => 'Автоуход', 'en' => 'Auto Care'],
        ]);

        return Service::create([
            'category_id' => $category->id,
            'slug' => 'car-interior-cleaning',
            'status' => 'active',
            'name' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'N'],
        ]);
    }

    public function test_only_published_videos_are_publicly_listed(): void
    {
        ServiceVideo::create(['video_path' => '/uploads/service-videos/a.mp4', 'is_published' => true]);
        ServiceVideo::create(['video_path' => '/uploads/service-videos/b.mp4', 'is_published' => false]);

        $this->getJson('/api/v1/service-videos')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_videos_can_be_filtered_by_service(): void
    {
        $service = $this->seedService();
        ServiceVideo::create(['service_id' => $service->id, 'video_path' => '/uploads/service-videos/a.mp4', 'is_published' => true]);
        ServiceVideo::create(['video_path' => '/uploads/service-videos/b.mp4', 'is_published' => true]);

        $this->getJson('/api/v1/service-videos?service=car-interior-cleaning')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_update_and_delete_a_video(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/admin/service-videos', [
            'video_path' => '/uploads/service-videos/a.mp4',
            'is_published' => false,
        ]);
        $response->assertCreated()->assertJsonPath('data.isPublished', false);
        $id = $response->json('data.id');

        $this->patchJson("/api/v1/admin/service-videos/{$id}", ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('data.isPublished', true);

        $this->deleteJson("/api/v1/admin/service-videos/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('service_videos', ['id' => $id]);
    }

    public function test_admin_index_lists_videos_regardless_of_publish_state(): void
    {
        Sanctum::actingAs(User::factory()->create());
        ServiceVideo::create(['video_path' => '/uploads/service-videos/a.mp4', 'is_published' => false]);

        $this->getJson('/api/v1/admin/service-videos')->assertOk()->assertJsonCount(1, 'data');
    }
}
