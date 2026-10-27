<?php

namespace App\Http\Resources\Admin;

use App\DataTables\Period;
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
            // Where the row is filed: its year, quarter, month, ISO week and day.
            'period' => ($at = $this->resource->getRawOriginal('occurred_at')) ? Period::tags($at) : null,
            'employeeId' => $this->employee_id,
            // A copy of that row of another table: counted once, there.
            'duplicateOf' => $this->duplicate_of_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
