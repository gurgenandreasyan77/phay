<?php

namespace App\Models\Concerns;

use Illuminate\Support\Arr;

trait HasTranslations
{
    /**
     * Resolve a JSON-translatable attribute for the given (or current) locale,
     * falling back to the app's fallback locale and then to any value present.
     */
    public function trans(string $field, ?string $locale = null): string
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return (string) ($value ?? '');
        }

        $locale ??= app()->getLocale();

        return $value[$locale]
            ?? $value[config('app.fallback_locale')]
            ?? (string) Arr::first($value, default: '');
    }

    /**
     * Same locale resolution as trans(), but for JSON-translatable
     * attributes whose per-locale value is itself a list (e.g. included
     * items, FAQ entries) rather than a plain string.
     */
    public function transList(string $field, ?string $locale = null): array
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return [];
        }

        $locale ??= app()->getLocale();

        return $value[$locale]
            ?? $value[config('app.fallback_locale')]
            ?? Arr::first($value, default: []);
    }
}
