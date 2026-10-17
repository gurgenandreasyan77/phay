<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact shape for catalog grids, related-product lists, and accessory
 * lists — mirrors the existing PostResource/PostSummaryResource split.
 */
class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $category = $this->primaryCategory();
        $image = $this->primaryImage();

        return [
            'id' => $this->id,
            'slug' => $this->localizedSlug($locale),
            'categorySlug' => $category?->localizedSlug($locale),
            'brand' => $this->brand,
            'name' => $this->trans('name'),
            'shortDescription' => $this->short_description ? $this->trans('short_description') : null,
            'image' => $image?->path ?? $this->image,
            'alt' => $image ? $image->trans('alt') : ($this->alt ? $this->trans('alt') : null),
            'volume' => $this->volume_value ? ['value' => (float) $this->volume_value, 'unit' => $this->volume_unit] : null,
            'priceAmount' => $this->price_amount,
            'oldPriceAmount' => $this->old_price_amount,
            'availabilityStatus' => $this->availability_status?->value,
        ];
    }
}
