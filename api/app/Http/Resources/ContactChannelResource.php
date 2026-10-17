<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
            'label' => $this->label,
            'isPrimary' => $this->is_primary,
        ];
    }
}
