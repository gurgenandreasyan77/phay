<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServicePackageRequest;
use App\Http\Requests\Admin\UpdateServicePackageRequest;
use App\Http\Resources\Admin\ServicePackageResource;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServicePackageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = ServicePackage::query()->orderBy('sort_order')->get();

        return ServicePackageResource::collection($items);
    }

    public function store(StoreServicePackageRequest $request): JsonResponse
    {
        $package = ServicePackage::create($request->validated());

        return (new ServicePackageResource($package->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateServicePackageRequest $request, ServicePackage $servicePackage): JsonResponse
    {
        $servicePackage->update($request->validated());

        return (new ServicePackageResource($servicePackage->fresh()))->response();
    }

    public function destroy(ServicePackage $servicePackage): Response
    {
        $servicePackage->delete();

        return response()->noContent();
    }
}
