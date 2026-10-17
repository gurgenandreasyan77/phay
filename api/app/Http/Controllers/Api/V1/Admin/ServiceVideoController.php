<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceVideoRequest;
use App\Http\Requests\Admin\UpdateServiceVideoRequest;
use App\Http\Resources\Admin\ServiceVideoResource;
use App\Models\ServiceVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceVideoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $videos = ServiceVideo::query()->orderBy('sort_order')->get();

        return ServiceVideoResource::collection($videos);
    }

    public function store(StoreServiceVideoRequest $request): JsonResponse
    {
        $video = ServiceVideo::create($request->validated());

        return (new ServiceVideoResource($video->fresh()))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceVideoRequest $request, ServiceVideo $serviceVideo): JsonResponse
    {
        $serviceVideo->update($request->validated());

        return (new ServiceVideoResource($serviceVideo->fresh()))->response();
    }

    public function destroy(ServiceVideo $serviceVideo): Response
    {
        $serviceVideo->delete();

        return response()->noContent();
    }
}
