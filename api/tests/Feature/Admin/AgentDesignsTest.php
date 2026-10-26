<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\Attachments;
use App\Assistant\ToolOutput;
use App\Media\DesignStudio;
use App\Media\Renderer;
use App\Models\AssistantAttachment;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Fakes\FakeRenderer;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Pictures the AI agents make: the designer writes HTML, the media service
 * renders it, the PNGs are kept with the answer (three variants to choose
 * from), the agent opens the chosen one again by its number, and the
 * owner downloads them.
 */
class AgentDesignsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    private FakeRenderer $renderer;

    private Tenant $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Attachments::DISK);
        $this->client = new FakeAssistantClient;
        $this->renderer = new FakeRenderer;
        $this->app->instance(AssistantClient::class, $this->client);
        $this->app->instance(Renderer::class, $this->renderer);
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->turnOn('designer');
    }

    private function turnOn(string $agent): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$agent}", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
    }

    private static function html(string $text, string $extra = ''): string
    {
        return "<!doctype html><html><body style=\"margin:0\"><div style=\"width:1080px;height:1350px\">{$extra}<h1>{$text}</h1></div></body></html>";
    }

    /** @return list<string> tool names the agent was given */
    private function tools(): array
    {
        return array_column($this->client->toolDefinitions ?? [], 'name');
    }

    public function test_the_designer_makes_three_variants_kept_with_the_answer(): void
    {
        $logo = $this->postJson('/api/v1/admin/agents/brand-assets', [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
            'kind' => 'logo',
        ])->assertCreated();

        $this->client->toolCalls = [[DesignStudio::RENDER, ['designs' => [
            ['title' => 'Մուգ կանաչ', 'format' => 'post', 'html' => self::html('Ակցիա', '<img src="{{logo}}">')],
            ['title' => 'Լուսավոր', 'format' => 'post', 'html' => self::html('Ակցիա 2')],
            ['title' => 'Սթորի', 'format' => 'story', 'html' => self::html('Ակցիա 3', '<img src="{{ file:999 }}">')],
        ]]]];

        $data = $this->postJson('/api/v1/admin/assistant', ['question' => 'Պատրաստիր պաստառ', 'agent' => 'designer'])
            ->assertOk()
            ->assertJsonCount(3, 'data.designs')
            ->assertJsonPath('data.designs.0.origin', 'design')
            ->assertJsonPath('data.designs.0.title', 'Մուգ կանաչ')
            ->assertJsonPath('data.designs.0.format', 'post')
            ->assertJsonPath('data.designs.0.mime', 'image/png')
            ->assertJsonPath('data.designs.2.format', 'story')
            ->json('data');

        // The agent knows how to design and has the tools.
        $this->assertStringContainsString('Making pictures:', $this->client->system);
        $this->assertContains(DesignStudio::RENDER, $this->tools());
        $this->assertContains(DesignStudio::GET, $this->tools());

        // Rendered at the format's size, the logo put in as a data: URI.
        $this->assertSame([[1080, 1350], [1080, 1350], [1080, 1920]], array_map(fn ($r) => [$r['width'], $r['height']], $this->renderer->renders));
        $this->assertStringContainsString('src="data:image/png;base64,', $this->renderer->renders[0]['html']);
        $this->assertStringNotContainsString('{{logo}}', $this->renderer->renders[0]['html']);
        $this->assertStringNotContainsString('file:999', $this->renderer->renders[2]['html']);

        // The agent sees what it made, numbered.
        $output = $this->client->toolResults[0];
        $this->assertInstanceOf(ToolOutput::class, $output);
        $this->assertCount(6, $output->blocks);
        $ids = array_column($data['designs'], 'id');
        $this->assertStringContainsString("[design #{$ids[0]}] «Մուգ կանաչ»", $output->blocks[0]['text']);
        $this->assertSame('image', $output->blocks[1]['type']);
        $this->assertStringContainsString('Not found, left empty: {{file:999}}', $output->blocks[4]['text']);

        // Kept privately, with the answer, its HTML not shown.
        $design = AssistantAttachment::withoutTenancy()->findOrFail($ids[0]);
        $this->assertStringStartsWith("assistant/{$this->shop->id}/", $design->path);
        Storage::disk(Attachments::DISK)->assertExists($design->path);
        $this->assertStringContainsString('{{logo}}', $design->html);

        $conversation = $this->getJson("/api/v1/admin/assistant/conversations/{$data['conversation']['id']}")
            ->assertOk()
            ->assertJsonCount(0, 'data.messages.0.attachments')
            ->assertJsonCount(3, 'data.messages.1.attachments')
            ->assertJsonPath('data.messages.1.attachments.1.title', 'Լուսավոր')
            ->assertJsonMissingPath('data.messages.1.attachments.0.html');

        // Downloaded as a file.
        $this->get("/api/v1/admin/assistant/attachments/{$ids[0]}/file?download=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload();

        // The owner picks one: the model sees the variants by number and opens it.
        $this->client->toolCalls = [[DesignStudio::GET, ['design' => $ids[1]]]];
        $this->client->toolResults = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => "Ընտրում եմ #{$ids[1]}", 'conversation_id' => $conversation->json('data.id')])
            ->assertOk()
            ->assertJsonCount(0, 'data.designs');

        $this->assertSame('assistant', $this->client->messages[1]['role']);
        $this->assertStringContainsString("[design #{$ids[1]}] «Լուսավոր» (post)", $this->client->messages[1]['content']);
        $this->assertSame(['design' => $ids[1], 'title' => 'Լուսավոր', 'format' => 'post', 'html' => self::html('Ակցիա 2')], $this->client->toolResults[0]);
    }

    public function test_a_picture_from_the_chat_goes_into_a_design(): void
    {
        $photo = $this->post('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->image('sofa.jpg', 400, 300)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        $this->client->toolCalls = [[DesignStudio::RENDER, ['designs' => [
            ['title' => 'Լուսանկարով', 'format' => 'square', 'html' => self::html('Բազմոց', "<div style=\"background:url({{file:{$photo}}})\"></div>")],
        ]]]];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Օգտագործիր այս նկարը', 'agent' => 'designer', 'attachments' => [$photo]])->assertOk();

        $this->assertStringContainsString('background:url(data:image/jpeg;base64,', $this->renderer->renders[0]['html']);
        $this->assertSame([1080, 1080], [$this->renderer->renders[0]['width'], $this->renderer->renders[0]['height']]);
    }

    /** The studio as one question uses it, in the shop. */
    private function studio(): DesignStudio
    {
        $this->app->make(TenantContext::class)->set($this->shop);

        return $this->app->make(DesignStudio::class);
    }

    /** The error the agent gets back for a bad call. */
    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->fail('The call was not refused.');
    }

    public function test_renders_are_checked_and_limited_per_question(): void
    {
        $studio = $this->studio();
        $design = fn (int $n) => ['title' => "V{$n}", 'format' => 'post', 'html' => self::html("V{$n}")];

        $this->assertStringContainsString('At most 3 designs', $this->refusal(fn () => $studio->render(['designs' => [$design(1), $design(2), $design(3), $design(4)]], $this->owner)));
        $this->assertStringContainsString('format must be one of', $this->refusal(fn () => $studio->render(['designs' => [['title' => 'x', 'format' => 'poster', 'html' => '<p>x</p>']]], $this->owner)));
        $this->assertStringContainsString('bigger than 200 KB', $this->refusal(fn () => $studio->render(['designs' => [['title' => 'x', 'format' => 'post', 'html' => str_repeat('x', 210 * 1024)]]], $this->owner)));
        $this->assertStringContainsString('kept chat', $this->refusal(fn () => $studio->render(['designs' => [$design(1)]], null)));
        $this->assertSame([], $this->renderer->renders);

        $studio->render(['designs' => [$design(1), $design(2), $design(3)]], $this->owner);
        $studio->render(['designs' => [$design(4), $design(5), $design(6)]], $this->owner);
        $this->assertStringContainsString('No more renders', $this->refusal(fn () => $studio->render(['designs' => [$design(7)]], $this->owner)));
        $this->assertCount(6, $studio->produced());
    }

    public function test_without_the_media_service_the_designer_gives_briefs(): void
    {
        $this->renderer->configured = false;

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Պաստառ', 'agent' => 'designer'])->assertOk()->assertJsonCount(0, 'data.designs');

        $this->assertNotContains(DesignStudio::RENDER, $this->tools());
        $this->assertContains('read_link', $this->tools());
        $this->assertStringNotContainsString('Making pictures:', $this->client->system);
    }

    public function test_a_failed_render_tells_the_agent_to_give_a_brief(): void
    {
        $this->renderer->fail = true;

        $message = $this->refusal(fn () => $this->studio()->render(['designs' => [['title' => 'x', 'format' => 'post', 'html' => self::html('x')]]], $this->owner));
        $this->assertStringContainsString('picture service is not available', $message);
        $this->assertSame(0, AssistantAttachment::withoutTenancy()->count());
    }

    public function test_agents_without_designs_only_read_links_and_designs_stay_private(): void
    {
        $this->turnOn('analyst');
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ինչպե՞ս են վաճառքները', 'agent' => 'analyst'])->assertOk();
        $this->assertContains('read_link', $this->tools());
        $this->assertNotContains(DesignStudio::RENDER, $this->tools());

        // A design of the owner: nobody else opens it, and it is no upload to attach.
        $this->client->toolCalls = [[DesignStudio::RENDER, ['designs' => [['title' => 'Իմը', 'format' => 'post', 'html' => self::html('x')]]]]];
        $id = $this->postJson('/api/v1/admin/assistant', ['question' => 'Պաստառ', 'agent' => 'designer'])->json('data.designs.0.id');

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Again', 'attachments' => [$id]])->assertUnprocessable();

        $other = User::factory()->forTenant($this->shop)->create();
        Sanctum::actingAs($other);
        $this->get("/api/v1/admin/assistant/attachments/{$id}/file")->assertNotFound();
        $this->assertStringContainsString('No such design', $this->refusal(fn () => $this->studio()->get(['design' => $id], $other)));
        $this->assertSame($id, $this->studio()->get(['design' => $id], $this->owner)['design']);
    }
}
