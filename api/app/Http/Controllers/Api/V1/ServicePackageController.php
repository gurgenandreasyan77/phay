<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicePackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categorySlug = $request->query('category');
        $packages = ServicePackage::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->when($categorySlug, function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->orderBy('sort_order')
            ->get();

        return ServicePackageResource::collection($packages);
    }

    public function show(string $slug): ServicePackageResource
    {
        $package = ServicePackage::query()
            ->where('slug', $slug)
            ->whereIn('status', array_map(fn ($s) => $s->value, ServiceStatus::publicallyVisible()))
            ->firstOrFail();

        return new ServicePackageResource($package);
    }
}
