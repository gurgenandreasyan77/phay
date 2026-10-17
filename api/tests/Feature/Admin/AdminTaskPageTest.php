<?php

namespace Tests\Feature\Admin;

use App\Models\MarketTaskPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTaskPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug_hy' => 'mizi-hoti-heracum',
            'slug_ru' => 'udaleniye-zapaha-mochi',
            'slug_en' => 'urine-odor-removal',
            'status' => 'draft',
            'title' => ['hy' => 'Վերնագիր', 'ru' => 'Заголовок', 'en' => 'Title'],
        ], $overrides);
    }

    public function test_store_creates_a_draft_page(): void
    {
        $this->postJson('/api/v1/admin/task-pages', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_store_rejects_publishing_without_intro_in_all_locales(): void
    {
        $payload = $this->payload([
            'status' => 'published',
            'intro' => ['hy' => 'Ներածություն', 'ru' => 'Введение', 'en' => ''],
        ]);

        $this->postJson('/api/v1/admin/task-pages', $payload)->assertStatus(422);
    }

    public function test_update_rejects_publishing_a_page_missing_intro(): void
    {
        $page = MarketTaskPage::create($this->payload());

        $this->patchJson("/api/v1/admin/task-pages/{$page->id}", ['status' => 'published'])
            ->assertStatus(422);
    }

    public function test_update_allows_publishing_once_intro_is_complete(): void
    {
        $page = MarketTaskPage::create($this->payload());

        $this->patchJson("/api/v1/admin/task-pages/{$page->id}", [
            'status' => 'published',
            'intro' => ['hy' => 'Ա', 'ru' => 'Б', 'en' => 'C'],
        ])->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_destroy_removes_the_page(): void
    {
        $page = MarketTaskPage::create($this->payload());

        $this->deleteJson("/api/v1/admin/task-pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('market_task_pages', ['id' => $page->id]);
    }
}
