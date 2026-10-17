<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Service;
use App\Models\ServiceSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    private const RELATION_KEYS = ['product_ids'];

    public function index(): AnonymousResourceCollection
    {
        $items = Service::query()
            ->with(['category', 'products'])
            ->orderBy('sort_order')
            ->get();

        return ServiceResource::collection($items);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = DB::transaction(function () use ($validated) {
            $service = Service::create(Arr::except($validated, self::RELATION_KEYS));
            $this->syncRelations($service, $validated);

            return $service;
        });

        return (new ServiceResource($this->freshWithRelations($service)))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $service) {
            $this->recordSlugChange($service, $validated);
            $service->update(Arr::except($validated, self::RELATION_KEYS));
            $this->syncRelations($service, $validated);
        });

        return (new ServiceResource($this->freshWithRelations($service)))->response();
    }

    public function destroy(Service $service): Response
    {
        $service->delete();

        return response()->noContent();
    }

    /**
     * Same pattern as Admin\ProductController@recordSlugChanges: whenever an
     * admin renames a service's single global slug, keep the old one
     * resolvable via a 301 by recording it in `service_slug_redirects`.
     */
    private function recordSlugChange(Service $service, array $validated): void
    {
        if (! array_key_exists('slug', $validated)) {
            return;
        }

        $oldSlug = $service->getOriginal('slug');
        $newSlug = $validated['slug'];

        if ($oldSlug && $oldSlug !== $newSlug) {
            ServiceSlugRedirect::query()->firstOrCreate([
                'old_slug' => $oldSlug,
            ], [
                'service_id' => $service->id,
            ]);
        }
    }

    private function syncRelations(Service $service, array $validated): void
    {
        if (array_key_exists('product_ids', $validated)) {
            $service->products()->sync($validated['product_ids']);
        }
    }

    private function freshWithRelations(Service $service): Service
    {
        return $service->fresh(['category', 'products']);
    }
}
