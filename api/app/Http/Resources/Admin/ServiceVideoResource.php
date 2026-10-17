<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceId' => $this->service_id,
            'title' => $this->title ?? (object) [],
            'videoPath' => $this->video_path,
            'posterImage' => $this->poster_image,
            'sortOrder' => $this->sort_order,
            'isPublished' => $this->is_published,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
