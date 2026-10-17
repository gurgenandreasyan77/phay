<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\ServiceStatus;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceSlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categorySlug = $request->query('category');
        $autoCareActive = Feature::active(FeatureFlag::AutoCare->value);

        // The category slug happens to equal the flag key ("auto-care") —
        // the two concepts are deliberately the same identifier here.
        if ($categorySlug === FeatureFlag::AutoCare->value && ! $autoCareActive) {
            throw new NotFoundHttpException;
        }

        $services = Service::query()
            ->with(['category', 'products' => fn ($query) => $query->where('status', ProductStatus::Published)])
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->when($categorySlug, function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->when(! $autoCareActive, function ($query) {
                $query->whereDoesntHave('category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->orderBy('sort_order')
            ->get();

        return ServiceResource::collection($services);
    }

    /**
     * Looked up by slug directly (not route-model binding) so a miss can
     * fall back to `service_slug_redirects` (populated whenever an admin
     * renames a service's slug — see Admin\ServiceController@recordSlugChange)
     * and report the current slug for a 301 instead of a 404 — same pattern
     * as ProductController@show.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $autoCareActive = Feature::active(FeatureFlag::AutoCare->value);

        $service = Service::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->where('slug', $slug)
            ->when(! $autoCareActive, function ($query) {
                $query->whereDoesntHave('category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->with(['category.services' => function ($query) {
                $query->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
                    ->orderBy('sort_order');
            }, 'products' => fn ($query) => $query->where('status', ProductStatus::Published), 'galleryItems'])
            ->first();

        if ($service) {
            $service->setRelation(
                'galleryItems',
                $service->galleryItems->filter(fn ($item) => $item->client_consent && $item->is_published)->values(),
            );

            return (new ServiceResource($service))->response();
        }

        $redirect = ServiceSlugRedirect::query()
            ->where('old_slug', $slug)
            ->with('service')
            ->first();

        $redirectTargetVisible = $redirect?->service
            && in_array($redirect->service->status, ServiceStatus::publicallyVisible(), true)
            && ($autoCareActive || $redirect->service->category?->slug !== FeatureFlag::AutoCare->value);

        if ($redirectTargetVisible) {
            return response()->json([
                'redirect' => ['slug' => $redirect->service->slug],
            ]);
        }

        return response()->json(['message' => 'Not found'], 404);
    }
}
