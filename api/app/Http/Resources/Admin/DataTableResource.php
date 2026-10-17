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
            'dateColumn' => $this->date_column,
            'amountColumn' => $this->amount_column,
            'quantityColumn' => $this->quantity_column,
            'rowsCount' => $this->whenCounted('rows'),
            'rowsWithoutDate' => $this->whenCounted('rows_without_date'),
            // When the first and last rows happened, the money of all rows, the last import.
            'firstAt' => $this->whenAggregated('rows', 'occurred_at', 'min', fn ($v) => $v ? substr((string) $v, 0, 16) : null),
            'lastAt' => $this->whenAggregated('rows', 'occurred_at', 'max', fn ($v) => $v ? substr((string) $v, 0, 16) : null),
            'amountTotal' => $this->whenAggregated('rows', 'amount', 'sum', fn ($v) => $v === null ? null : round((float) $v, 2)),
            'lastImportAt' => $this->whenAggregated('imports', 'created_at', 'max', fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->toIso8601String() : null),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
