<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\Attachments;
use App\Media\Renderer;
use App\Media\VideoCutter;
use App\Media\VideoStudio;
use App\Models\AssistantAttachment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoJob;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Fakes\FakeRenderer;
use Tests\Fakes\FakeVideoCutter;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Videos the AI agents cut: the owner attaches clips (the model sees a few
 * frames of each), the editor's plan is checked and started in the media
 * service, the chat asks about it until the MP4 is collected and kept with
 * the answer, and the next question carries the plan so it can be changed.
 */
class AgentVideosTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    private FakeRenderer $renderer;

    private FakeVideoCutter $cutter;

    private Tenant $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Attachments::DISK);
        $this->client = new FakeAssistantClient;
        $this->renderer = new FakeRenderer;
        $this->cutter = new FakeVideoCutter;
        $this->app->instance(AssistantClient::class, $this->client);
        $this->app->instance(Renderer::class, $this->renderer);
        $this->app->instance(VideoCutter::class, $this->cutter);
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->turnOn('editor');
    }

    private function turnOn(string $agent): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$agent}", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
    }

    /** A file that is an MP4 by its content. */
    private static function mp4(string $name = 'clip.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 256));
    }

    private function upload(?UploadedFile $file = null)
    {
        return $this->post('/api/v1/admin/assistant/attachments', ['file' => $file ?? self::mp4()], ['Accept' => 'application/json']);
    }

    /** @return list<string> tool names the agent was given */
    private function tools(): array
    {
        return array_column($this->client->toolDefinitions ?? [], 'name');
    }

    /** The studio as a new question uses it, in the shop. */
    private function studio(): VideoStudio
    {
        $this->app->forgetScopedInstances();
        $this->app->make(TenantContext::class)->set($this->shop);

        return $this->app->make(VideoStudio::class);
    }

    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->fail('The call was not refused.');
    }

    public function test_a_clip_is_probed_and_the_model_sees_its_frames(): void
    {
        $data = $this->upload()
            ->assertCreated()
            ->assertJsonPath('data.kind', 'video')
            ->assertJsonPath('data.mime', 'video/mp4')
            ->assertJsonPath('data.duration', 12.5)
            ->assertJsonPath('data.width', 1080)
            ->assertJsonPath('data.height', 1920)
            ->assertJsonMissingPath('data.meta')
            ->json('data');

        $clip = AssistantAttachment::withoutTenancy()->findOrFail($data['id']);
        $this->assertCount(2, $clip->meta['frames']);
        foreach ($clip->paths() as $path) {
            Storage::disk(Attachments::DISK)->assertExists($path);
        }

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ի՞նչ կա այստեղ', 'attachments' => [$data['id']]])->assertOk();

        $blocks = $this->client->messages[0]['content'];
        $this->assertSame("[file #{$data['id']}] at 1.6 s:", $blocks[0]['text']);
        $this->assertSame(['type' => 'base64', 'media_type' => 'image/jpeg'], array_intersect_key($blocks[1]['source'], ['type' => 1, 'media_type' => 1]));
        $this->assertCount(5, $blocks);
        $this->assertStringContainsString("[file #{$data['id']}: clip.mp4, video, 12.5 s, 1080×1920, with sound]", $blocks[4]['text']);

        // Taken back before sending: the frames go too.
        $other = $this->upload()->json('data.id');
        $paths = AssistantAttachment::withoutTenancy()->findOrFail($other)->paths();
        $this->deleteJson("/api/v1/admin/assistant/attachments/{$other}")->assertNoContent();
        foreach ($paths as $path) {
            Storage::disk(Attachments::DISK)->assertMissing($path);
        }
    }

    public function test_clips_are_refused_when_unreadable_too_long_or_without_the_service(): void
    {
        $this->cutter->fail = 422;
        $this->upload()->assertUnprocessable()->assertJsonPath('errors.file.0', 'Տեսանյութը չի բացվում։ Փորձեք այլ ֆայլ (MP4 կամ MOV)։');

        $this->cutter->fail = 503;
        $this->upload()->assertUnprocessable()->assertJsonPath('errors.file.0', 'Տեսանյութերի ծառայությունը հիմա հասանելի չէ։ Փորձեք մի փոքր ուշ։');

        $this->cutter->fail = null;
        $this->cutter->clip['duration'] = 240;
        $this->upload()->assertUnprocessable()->assertJsonPath('errors.file.0', 'Տեսանյութը պետք է լինի մինչև 3 րոպե։ Կտրեք այն կամ ուղարկեք մասերով։');

        $this->cutter->configured = false;
        $this->upload()->assertUnprocessable()->assertJsonPath('errors.file.0', 'Տեսանյութեր դեռ չեն ընդունվում։ Կցեք մի քանի կադր որպես նկար։');

        // Nothing kept of the refused ones.
        $this->assertSame(0, AssistantAttachment::withoutTenancy()->count());
        $this->assertSame([], Storage::disk(Attachments::DISK)->allFiles());
    }

    public function test_the_editor_cuts_a_video_collected_when_ready(): void
    {
        $this->renderer->fail = false;
        $a = $this->upload()->json('data.id');
        $b = $this->upload(self::mp4('b.mp4'))->json('data.id');

        $probed = count($this->cutter->uploads);
        $this->client->toolCalls = [[VideoStudio::EDIT, [
            'title' => 'Բազմոցի մաքրում',
            'format' => 'reel',
            'parts' => [
                ['file' => $b, 'start' => 1, 'end' => 4],
                ['file' => $a, 'start' => 2, 'end' => 12, 'speed' => 2, 'muted' => true],
                ['file' => $b, 'start' => 6, 'end' => 50],
            ],
            'texts' => [
                ['text' => "Մինչև\nև հետո", 'start' => 0, 'end' => 2.5, 'position' => 'top', 'style' => 'title'],
                ['text' => 'Բազմոց՝ 5900 դր', 'start' => 9, 'end' => 99, 'style' => 'pill'],
            ],
        ]]];

        $data = $this->postJson('/api/v1/admin/assistant', ['question' => 'Մոնտաժիր ռիլս', 'agent' => 'editor', 'attachments' => [$a, $b]])
            ->assertOk()
            ->assertJsonCount(1, 'data.videos')
            ->assertJsonPath('data.videos.0.status', 'rendering')
            ->assertJsonPath('data.videos.0.title', 'Բազմոցի մաքրում')
            ->assertJsonPath('data.videos.0.attachment', null)
            ->json('data');

        $this->assertStringContainsString('Cutting videos:', $this->client->system);
        $this->assertContains(VideoStudio::EDIT, $this->tools());

        // 3 + 10/2 + (12.5 - 6) = 14.5 s; each clip uploaded once.
        $video = VideoJob::withoutTenancy()->findOrFail($data['videos'][0]['id']);
        $this->assertSame(14.5, $video->duration);
        $this->assertCount($probed + 2, $this->cutter->uploads);
        $plan = $this->cutter->plans[0];
        $this->assertSame([1080, 1920], [$plan['width'], $plan['height']]);
        $this->assertSame(['file-'.($probed + 1), 2.0, 12.0, 2.0, true], [$plan['clips'][1]['file'], $plan['clips'][1]['start'], $plan['clips'][1]['end'], $plan['clips'][1]['speed'], $plan['clips'][1]['muted']]);
        $this->assertSame(12.5, $plan['clips'][2]['end']);
        $this->assertSame([9.0, 14.5], [$plan['overlays'][1]['start'], $plan['overlays'][1]['end']]);
        $this->assertStringContainsString("[video #{$video->id}]", $this->client->toolResults[0]['note']);

        // Texts drawn see-through at the video's size, in the brand's colours, escaped.
        $this->assertCount(2, $this->renderer->renders);
        $this->assertTrue($this->renderer->renders[0]['transparent']);
        $this->assertSame([1080, 1920], [$this->renderer->renders[0]['width'], $this->renderer->renders[0]['height']]);
        $this->assertStringContainsString('Մինչև<br>', $this->renderer->renders[0]['html']);

        // Still rendering: the chat sees the progress.
        $this->getJson("/api/v1/admin/assistant/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'rendering')
            ->assertJsonPath('data.progress', 40);

        // Ready: collected as an MP4 of the answer.
        $this->cutter->status = ['status' => 'done', 'progress' => 100, 'error' => null];
        $file = $this->getJson("/api/v1/admin/assistant/videos/{$video->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.attachment.kind', 'video')
            ->assertJsonPath('data.attachment.origin', 'video')
            ->assertJsonPath('data.attachment.duration', 14.5)
            ->json('data.attachment');

        $this->get("/api/v1/admin/assistant/attachments/{$file['id']}/file?download=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertDownload();
        // Played in ranges.
        $this->get("/api/v1/admin/assistant/attachments/{$file['id']}/file", ['Range' => 'bytes=0-3'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-3/'.$file['size']);

        $conversation = $this->getJson("/api/v1/admin/assistant/conversations/{$data['conversation']['id']}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages.0.attachments')
            ->assertJsonCount(0, 'data.messages.1.attachments')
            ->assertJsonPath('data.messages.1.videos.0.status', 'done')
            ->assertJsonPath('data.messages.1.videos.0.attachment.id', $file['id']);

        // The next question carries the plan, to change it.
        $this->client->toolCalls = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Կարճացրու', 'conversation_id' => $conversation->json('data.id')])->assertOk();
        $answer = $this->client->messages[1]['content'];
        $this->assertStringContainsString("[video #{$video->id}] «Բազմոցի մաքրում» (reel, 14.5 s, done)", $answer);
        $this->assertStringContainsString('"speed":2', $answer);

        // Deleting the conversation removes the clips, their frames and the video.
        $paths = AssistantAttachment::withoutTenancy()->get()->flatMap(fn ($f) => $f->paths())->all();
        $this->assertCount(7, $paths);
        $this->deleteJson("/api/v1/admin/assistant/conversations/{$conversation->json('data.id')}")->assertNoContent();
        $this->assertSame([], Storage::disk(Attachments::DISK)->allFiles());
        $this->assertSame(0, VideoJob::withoutTenancy()->count());
    }

    public function test_the_scheduler_collects_videos_and_marks_lost_ones_failed(): void
    {
        $clip = $this->upload()->json('data.id');
        $studio = $this->studio();
        $studio->edit(['title' => 'A', 'format' => 'square', 'parts' => [['file' => $clip, 'start' => 0, 'end' => 5]]], $this->owner);
        $studio->edit(['title' => 'B', 'format' => 'feed', 'parts' => [['file' => $clip, 'start' => 0, 'end' => 5]]], $this->owner);
        [$a, $b] = $studio->started();

        $this->cutter->status = ['status' => 'done', 'progress' => 100, 'error' => null];
        $this->artisan('platform:videos:collect')->assertSuccessful();
        $this->assertSame(VideoJob::DONE, $a->fresh()->status);
        $attachment = $a->fresh()->attachment;
        $this->assertSame([1080, 1080], [$attachment->width, $attachment->height]);
        $this->assertSame($this->shop->id, $attachment->tenant_id);

        VideoJob::withoutTenancy()->whereKey($b->id)->update(['status' => VideoJob::RENDERING, 'attachment_id' => null]);
        $this->cutter->status = ['status' => 'gone', 'progress' => 0, 'error' => null];
        $this->artisan('platform:videos:collect')->assertSuccessful();
        $this->assertSame(VideoJob::FAILED, $b->fresh()->status);
        $this->assertStringContainsString('restarted', $b->fresh()->error);
    }

    public function test_plans_are_checked_and_videos_stay_private(): void
    {
        $clip = $this->upload()->json('data.id');
        $studio = $this->studio();
        $part = ['file' => $clip, 'start' => 0, 'end' => 5];

        $this->assertStringContainsString('format must be one of', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'tv', 'parts' => [$part]], $this->owner)));
        $this->assertStringContainsString('no such video', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => [[...$part, 'file' => 999]]], $this->owner)));
        $this->assertStringContainsString('inside the clip', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => [[...$part, 'start' => 13, 'end' => 20]]], $this->owner)));
        $this->assertStringContainsString('at most 90 s', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => array_fill(0, 10, [...$part, 'end' => 12, 'speed' => 0.5])], $this->owner)));
        $this->assertStringContainsString('1 to 160 characters', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => [$part], 'texts' => [['text' => ' ', 'start' => 0, 'end' => 1]]], $this->owner)));

        // The service refuses: the agent is told to give the plan.
        $this->cutter->fail = 503;
        $this->assertStringContainsString('not available right now', $this->refusal(fn () => $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => [$part]], $this->owner)));
        $this->cutter->fail = null;
        $this->assertSame(0, VideoJob::withoutTenancy()->count());

        $studio->edit(['title' => 'x', 'format' => 'reel', 'parts' => [$part]], $this->owner);
        $studio->edit(['title' => 'y', 'format' => 'reel', 'parts' => [$part]], $this->owner);
        $this->assertStringContainsString('No more videos', $this->refusal(fn () => $studio->edit(['title' => 'z', 'format' => 'reel', 'parts' => [$part]], $this->owner)));

        // Someone else's clip or video: not theirs to use or see.
        $other = User::factory()->forTenant($this->shop)->create();
        $this->assertStringContainsString('no such video', $this->refusal(fn () => $this->studio()->edit(['title' => 'x', 'format' => 'reel', 'parts' => [$part]], $other)));
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/admin/assistant/videos/'.$studio->started()[0]->id)->assertNotFound();
    }

    public function test_only_the_editors_cut_and_only_with_the_service(): void
    {
        $this->turnOn('analyst');
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Վաճառքներ', 'agent' => 'analyst'])->assertOk();
        $this->assertNotContains(VideoStudio::EDIT, $this->tools());
        $this->assertStringContainsString('edit videos', $this->client->system);

        $this->turnOn('reels');
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ռիլս', 'agent' => 'reels'])->assertOk();
        $this->assertContains(VideoStudio::EDIT, $this->tools());

        $this->cutter->configured = false;
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Մոնտաժ', 'agent' => 'editor'])->assertOk();
        $this->assertNotContains(VideoStudio::EDIT, $this->tools());
        $this->assertStringNotContainsString('Cutting videos:', $this->client->system);
    }
}
