<?php

namespace App\Http\Resources\Admin;

use App\Models\DataTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DataTable */
class DataTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section' => $this->section->value,
            'name' => $this->name,
            'description' => $this->description,
            'columns' => $this->columns,
            'rowsCount' => $this->whenCounted('rows'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
