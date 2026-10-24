<?php

namespace Tests\Feature\Tenancy;

use App\Models\Post;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commands for moving the platform to its own address: listing the sites'
 * keys and rewriting stored file URLs.
 */
class PlatformMoveCommandsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    public function test_site_list_prints_every_key(): void
    {
        $site = Tenant::default()->sites()->sole();

        $this->artisan('platform:site:list')
            ->expectsOutputToContain($site->public_key)
            ->assertSuccessful();
    }

    public function test_rehost_rewrites_plain_json_and_html_columns_and_leaves_other_hosts(): void
    {
        $tenant = Tenant::default();
        $tenant->update(['logo' => 'https://old.example.am/storage/logos/a.png']);
        $tenant->sites()->sole()->update(['domain' => 'old.example.am']);
        $post = Post::query()->create([
            'slug' => 'tips',
            'category' => 'cleaning-tips',
            'status' => 'published',
            'title' => ['hy' => 'Խորհուրդ', 'ru' => 'Совет', 'en' => 'Tip'],
            'excerpt' => ['hy' => 'a', 'ru' => 'a', 'en' => 'a'],
            'content' => ['hy' => '<img src="https://old.example.am/storage/p/1.jpg">', 'ru' => '<p>https://cdn.example/x.jpg</p>', 'en' => 'b'],
            'published_at' => now(),
        ]);

        $this->artisan('platform:media:rehost', ['from' => 'https://old.example.am', 'to' => 'https://api.phay.am', '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertSame('https://old.example.am/storage/logos/a.png', $tenant->fresh()->logo);

        $this->artisan('platform:media:rehost', ['from' => 'https://old.example.am/', 'to' => 'https://api.phay.am'])->assertSuccessful();

        $this->assertSame('https://api.phay.am/storage/logos/a.png', $tenant->fresh()->logo);
        $this->assertSame('<img src="https://api.phay.am/storage/p/1.jpg">', $post->fresh()->content['hy']);
        $this->assertSame('<p>https://cdn.example/x.jpg</p>', $post->fresh()->content['ru']);
        // Site domains are names, not URLs: untouched.
        $this->assertSame('old.example.am', Site::query()->where('tenant_id', $tenant->id)->value('domain'));
    }

    public function test_rehost_refuses_nonsense(): void
    {
        $this->artisan('platform:media:rehost', ['from' => 'old.example.am', 'to' => 'https://api.phay.am'])->assertFailed();
    }
}
