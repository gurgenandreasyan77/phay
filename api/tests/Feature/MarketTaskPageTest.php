<?php

namespace Tests\Feature;

use App\Models\MarketTaskPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketTaskPageTest extends TestCase
{
    use RefreshDatabase;

    private function makePage(array $overrides = []): MarketTaskPage
    {
        return MarketTaskPage::create(array_merge([
            'slug_hy' => 'mizi-hoti-heracum',
            'slug_ru' => 'udaleniye-zapaha-mochi',
            'slug_en' => 'urine-odor-removal',
            'status' => 'published',
            'title' => ['hy' => 'Վերնագիր', 'ru' => 'Заголовок', 'en' => 'Title'],
            'intro' => ['hy' => 'Ներածություն', 'ru' => 'Введение', 'en' => 'Intro'],
        ], $overrides));
    }

    public function test_index_lists_only_published_pages(): void
    {
        $this->makePage();
        $this->makePage(['slug_hy' => 'draft-hy', 'slug_ru' => 'draft-ru', 'slug_en' => 'draft-en', 'status' => 'draft']);

        $this->getJson('/api/v1/task-pages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_resolves_by_locale_slug(): void
    {
        $this->makePage();

        $this->getJson('/api/v1/task-pages/udaleniye-zapaha-mochi?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.title', 'Заголовок');
    }

    public function test_show_returns_404_for_a_draft_page(): void
    {
        $this->makePage(['status' => 'draft']);

        $this->getJson('/api/v1/task-pages/urine-odor-removal')->assertNotFound();
    }

    public function test_index_eager_loads_products_so_it_is_never_missing_from_the_response(): void
    {
        $this->makePage();

        $this->getJson('/api/v1/task-pages')
            ->assertOk()
            ->assertJsonPath('data.0.products', []);
    }
}
