<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskPageRequest;
use App\Http\Requests\Admin\UpdateTaskPageRequest;
use App\Http\Resources\Admin\MarketTaskPageResource;
use App\Models\MarketTaskPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class TaskPageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $pages = MarketTaskPage::query()->with('products')->orderBy('sort_order')->get();

        return MarketTaskPageResource::collection($pages);
    }

    public function store(StoreTaskPageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = MarketTaskPage::create(Arr::except($validated, ['product_ids']));

        if (array_key_exists('product_ids', $validated)) {
            $page->products()->sync($validated['product_ids']);
        }

        return (new MarketTaskPageResource($page->fresh('products')))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskPageRequest $request, MarketTaskPage $taskPage): JsonResponse
    {
        $validated = $request->validated();
        $taskPage->update(Arr::except($validated, ['product_ids']));

        if (array_key_exists('product_ids', $validated)) {
            $taskPage->products()->sync($validated['product_ids']);
        }

        return (new MarketTaskPageResource($taskPage->fresh('products')))->response();
    }

    public function destroy(MarketTaskPage $taskPage): Response
    {
        $taskPage->delete();

        return response()->noContent();
    }
}
