<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceStatus;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServicePackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categorySlug = $request->query('category');
        $autoCareActive = Feature::active(FeatureFlag::AutoCare->value);

        if ($categorySlug === FeatureFlag::AutoCare->value && ! $autoCareActive) {
            throw new NotFoundHttpException;
        }

        $packages = ServicePackage::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->when($categorySlug, function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->when(! $autoCareActive, function ($query) {
                $query->whereDoesntHave('category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->orderBy('sort_order')
            ->get();

        return ServicePackageResource::collection($packages);
    }

    public function show(string $slug): ServicePackageResource
    {
        $autoCareActive = Feature::active(FeatureFlag::AutoCare->value);

        $package = ServicePackage::query()
            ->where('slug', $slug)
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->when(! $autoCareActive, function ($query) {
                $query->whereDoesntHave('category', fn ($q) => $q->where('slug', FeatureFlag::AutoCare->value));
            })
            ->firstOrFail();

        return new ServicePackageResource($package);
    }
}
