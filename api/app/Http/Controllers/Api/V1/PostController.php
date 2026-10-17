<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostSummaryResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->when($request->query('category'), function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($request->query('service'), function ($query, $serviceSlug) {
                $query->whereHas('services', fn ($q) => $q->where('slug', $serviceSlug));
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return PostSummaryResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        $post->load('services');

        return new PostResource($post);
    }
}
