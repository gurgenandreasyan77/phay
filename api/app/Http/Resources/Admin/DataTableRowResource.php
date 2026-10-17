<?php

namespace App\Http\Resources\Admin;

use App\Models\DataTableRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DataTableRow */
class DataTableRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Not `data`: JsonResource would then skip its own `data` wrapper.
            // An object even when empty, so the admin can always index it by column key.
            'values' => (object) ($this->data ?? []),
            'source' => $this->source,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
