<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SiteVideoKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceVideoResource;
use App\Models\ServiceVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceVideoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $videos = ServiceVideo::query()
            ->published()
            ->when(SiteVideoKind::tryFrom((string) $request->query('kind')), fn ($query, SiteVideoKind $kind) => $query->where('kind', $kind))
            ->when($request->query('service'), function ($query, $serviceSlug) {
                $query->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug));
            })
            ->orderBy('sort_order')
            ->get();

        return ServiceVideoResource::collection($videos);
    }
}
