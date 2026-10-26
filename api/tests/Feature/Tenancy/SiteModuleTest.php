<?php

namespace Tests\Feature\Tenancy;

use App\Models\AdminNotification;
use App\Models\FeedbackMessage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Site management: a module the platform turns on for a business it gave a
 * site to. The business keeps its videos, blog and the messages of its
 * feedback form there.
 */
class SiteModuleTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function postPayload(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'sofa-care',
            'category' => 'upholstery-care',
            'status' => 'published',
            'title' => ['hy' => 'Ինչպես խնամել բազմոցը', 'ru' => '', 'en' => null],
            'excerpt' => ['hy' => 'Կարճ'],
            'content' => ['hy' => '<p onclick="steal()">Մաքուր <a href="javascript:alert(1)">հղում</a> <a href="/hy/services">ծառայություններ</a></p><script>alert(1)</script><h2>Վերնագիր</h2>'],
        ], $overrides);
    }

    public function test_a_business_without_the_module_has_no_site_content(): void
    {
        $shop = $this->createTenant('test-shop');
        $key = $this->createSite($shop)->public_key;
        Sanctum::actingAs(User::factory()->forTenant($shop)->create());

        $this->getJson('/api/v1/posts', ['X-Site-Key' => $key])->assertNotFound();
        $this->postJson('/api/v1/feedback', ['name' => 'Աննա', 'phone' => '+37491000000', 'message' => 'Բարև'], ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/admin/posts')->assertNotFound();
        $this->getJson('/api/v1/admin/feedback')->assertNotFound();
        $this->getJson('/api/v1/admin/service-videos')->assertNotFound();

        Feature::for($shop)->activate('site');

        $this->getJson('/api/v1/posts', ['X-Site-Key' => $key])->assertOk();
        $this->getJson('/api/v1/admin/posts')->assertOk();
        $this->getJson('/api/v1/admin/service-videos')->assertOk();
    }

    public function test_the_business_writes_its_blog_with_safe_formatting_only(): void
    {
        $shop = $this->createTenant('test-shop');
        Feature::for($shop)->activate('site');
        $key = $this->createSite($shop)->public_key;
        Sanctum::actingAs(User::factory()->forTenant($shop)->create());

        $post = $this->postJson('/api/v1/admin/posts', $this->postPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', ['hy' => 'Ինչպես խնամել բազմոցը'])
            ->assertJsonPath('data.authorName', 'Test Shop')
            ->json('data');

        $this->assertSame(
            '<p>Մաքուր <a>հղում</a> <a href="/hy/services">ծառայություններ</a></p><h2>Վերնագիր</h2>',
            $post['content']['hy'],
        );
        $this->assertNotNull($post['publishedAt']);

        // Same slug in another business is fine, twice in one is not.
        $this->postJson('/api/v1/admin/posts', $this->postPayload())->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/admin/posts', $this->postPayload(['slug' => 'Bad Slug']))->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson('/api/v1/admin/posts', $this->postPayload(['slug' => 'empty', 'content' => ['hy' => '<script>x</script>']]))
            ->assertUnprocessable()->assertJsonValidationErrors('content.hy');

        // The site shows it; a draft only in the admin.
        $this->getJson('/api/v1/posts/sofa-care?lang=ru', ['X-Site-Key' => $key])->assertOk()->assertJsonPath('data.title', 'Ինչպես խնամել բազմոցը');
        $this->patchJson("/api/v1/admin/posts/{$post['id']}", ['status' => 'draft'])->assertOk();
        $this->getJson('/api/v1/posts/sofa-care', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson("/api/v1/admin/posts/{$post['id']}")->assertOk()->assertJsonPath('data.status', 'draft');

        // The default business's posts stay its own.
        $this->assertSame(1, Post::withoutTenancy()->where('tenant_id', $shop->id)->count());
        $this->deleteJson("/api/v1/admin/posts/{$post['id']}")->assertNoContent();
        $this->assertSame(0, Post::withoutTenancy()->where('tenant_id', $shop->id)->count());
    }

    public function test_feedback_from_the_site_reaches_the_business(): void
    {
        $shop = $this->createTenant('test-shop');
        Feature::for($shop)->activate('site');
        $key = $this->createSite($shop)->public_key;
        $owner = User::factory()->forTenant($shop)->create();

        $this->postJson('/api/v1/feedback', ['name' => 'Աննա', 'message' => 'Բարև'], ['X-Site-Key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'email']);
        $this->postJson('/api/v1/feedback', ['name' => 'Աննա', 'phone' => '+374 91 123456', 'message' => 'Շնորհակալություն', 'locale' => 'hy'], ['X-Site-Key' => $key])
            ->assertCreated();
        // A bot filling the hidden field is told it worked, nothing is kept.
        $this->postJson('/api/v1/feedback', ['name' => 'Bot', 'email' => 'b@b.am', 'message' => 'spam', 'website' => 'x'], ['X-Site-Key' => $key])
            ->assertCreated();

        $this->assertSame(1, FeedbackMessage::withoutTenancy()->where('tenant_id', $shop->id)->count());
        $this->assertSame('feedback', AdminNotification::query()->where('user_id', $owner->id)->value('type'));

        Sanctum::actingAs($owner);
        $message = $this->getJson('/api/v1/admin/feedback')
            ->assertOk()
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.name', 'Աննա')
            ->json('data.0');

        $this->patchJson("/api/v1/admin/feedback/{$message['id']}", ['read' => true])->assertOk()->assertJsonPath('data.id', $message['id']);
        $this->getJson('/api/v1/admin/feedback')->assertJsonPath('meta.unread', 0);
        $this->deleteJson("/api/v1/admin/feedback/{$message['id']}")->assertNoContent();

        // Another business doesn't see them.
        $other = $this->createTenant('other-shop');
        Feature::for($other)->activate('site');
        Sanctum::actingAs(User::factory()->forTenant($other)->create());
        $this->getJson('/api/v1/admin/feedback')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_videos_are_before_after_or_informational(): void
    {
        Sanctum::actingAs(User::factory()->create()); // owner of the default business

        $this->postJson('/api/v1/admin/service-videos', ['video_path' => '/uploads/a.mp4', 'kind' => 'before_after', 'is_published' => true])
            ->assertCreated()->assertJsonPath('data.kind', 'before_after');
        $this->postJson('/api/v1/admin/service-videos', ['video_path' => '/uploads/b.mp4', 'is_published' => true])
            ->assertCreated()->assertJsonPath('data.kind', 'info');
        $this->postJson('/api/v1/admin/service-videos', ['video_path' => '/uploads/c.mp4', 'kind' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('kind');

        $this->getJson('/api/v1/service-videos?kind=before_after')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'before_after');
        $this->getJson('/api/v1/service-videos')->assertOk()->assertJsonCount(2, 'data');
    }
}
