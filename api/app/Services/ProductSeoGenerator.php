<?php

namespace App\Services;

use App\Models\Product;

/**
 * Safe, factual SEO title/description fallback for products whose admin
 * hasn't filled in `seo_title`/`seo_description` yet. Only combines fields
 * that are already stored on the product (name, brand, category, short
 * description) — it never invents claims, prices, or availability. The
 * admin's own value (when present) always wins; this is a display-time
 * fallback only and is never written back to the database.
 */
class ProductSeoGenerator
{
    public function title(Product $product, string $locale): string
    {
        $stored = $product->trans('seo_title', $locale);
        if ($stored !== '') {
            return $stored;
        }

        $name = trim(($product->brand ? $product->brand.' ' : '').$product->trans('name', $locale));
        $category = $product->primaryCategory()?->trans('name', $locale);

        return $category ? "{$name} — {$category}" : $name;
    }

    public function description(Product $product, string $locale): string
    {
        $stored = $product->trans('seo_description', $locale);
        if ($stored !== '') {
            return $stored;
        }

        $short = $product->trans('short_description', $locale);
        if ($short !== '') {
            return $short;
        }

        $description = $product->trans('description', $locale);

        return mb_strlen($description) > 160
            ? rtrim(mb_substr($description, 0, 157)).'...'
            : $description;
    }
}
