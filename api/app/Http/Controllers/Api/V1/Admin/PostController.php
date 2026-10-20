<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostRequest;
use App\Models\Post;
use App\Support\HtmlSanitizer;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/** The blog of the business's site (the site module), drafts included. */
class PostController extends Controller
{
    private const LOCALES = ['hy', 'ru', 'en'];

    public function index(): JsonResponse
    {
        $posts = Post::query()->latest('published_at')->latest('id')->get();

        return response()->json(['data' => $posts->map(fn (Post $post) => $this->present($post, false))]);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json(['data' => $this->present($post)]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create($this->attributes($request->validated()));

        return response()->json(['data' => $this->present($post->fresh())], 201);
    }

    public function update(StorePostRequest $request, Post $post): JsonResponse
    {
        $post->update($this->attributes($request->validated(), $post));

        return response()->json(['data' => $this->present($post->fresh())]);
    }

    public function destroy(Post $post): Response
    {
        $post->delete();

        return response()->noContent();
    }

    /**
     * Texts keep only the languages filled in, the content only safe
     * formatting; publishing without a date publishes now.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Post $post = null): array
    {
        foreach (['title', 'excerpt', 'content', 'seo_title', 'seo_description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $texts = collect(Arr::only((array) $data[$field], self::LOCALES))
                ->map(fn ($text) => trim((string) $text))
                ->map(fn (string $text) => $field === 'content' ? HtmlSanitizer::clean($text) : $text)
                ->filter(fn (string $text) => $text !== '');

            if ($field === 'content' && ! $texts->has('hy')) {
                throw ValidationException::withMessages(['content.hy' => 'Հոդվածի տեքստը դատարկ է։']);
            }

            $data[$field] = $texts->isEmpty() ? null : $texts->all();
        }

        // Signed by the business unless someone else is named.
        if (($post === null || array_key_exists('author_name', $data)) && blank($data['author_name'] ?? null)) {
            $data['author_name'] = app(TenantContext::class)->current()->name;
        }

        $status = PostStatus::tryFrom((string) ($data['status'] ?? $post?->status?->value));
        if ($status === PostStatus::Published && empty($data['published_at']) && ! $post?->published_at) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(Post $post, bool $full = true): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'category' => $post->category->value,
            'status' => $post->status->value,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'authorName' => $post->author_name,
            'featuredImagePath' => $post->featured_image_path,
            'title' => $post->title ?? (object) [],
            'excerpt' => $post->excerpt ?? (object) [],
            'updatedAt' => $post->updated_at?->toIso8601String(),
        ] + ($full ? [
            'content' => $post->content ?? (object) [],
            'seoTitle' => $post->seo_title ?? (object) [],
            'seoDescription' => $post->seo_description ?? (object) [],
        ] : []);
    }
}
