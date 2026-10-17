<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ? $this->trans('title') : null,
            'videoPath' => $this->video_path,
            'posterImage' => $this->poster_image,
        ];
    }
}
