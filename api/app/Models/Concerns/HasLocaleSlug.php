<?php

namespace App\Models\Concerns;

/**
 * For models with per-locale slug columns (slug_hy/slug_ru/slug_en) instead
 * of one slug shared across locales. Unlike Service/Post (single global
 * slug), Product/ProductCategory/MarketTaskPage need an independently
 * editable, uniquely-constrained slug per locale (see market plan).
 */
trait HasLocaleSlug
{
    public static function slugColumn(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($locale) {
            'ru' => 'slug_ru',
            'en' => 'slug_en',
            default => 'slug_hy',
        };
    }

    public function localizedSlug(?string $locale = null): string
    {
        return (string) ($this->getAttribute(static::slugColumn($locale)) ?? '');
    }
}
