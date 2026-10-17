<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks a "published" status unless the given translatable field (an
 * hy/ru/en array) has real content in all three locales. This is the "don't
 * publish machine-translated or half-empty content" gate from the market
 * spec — used for Product (name/short_description/description) and
 * MarketTaskPage (title/intro) alike, while leaving SEO title/description
 * out of the gate entirely, since ProductSeoGenerator always derives a safe
 * fallback for those from already-required fields.
 */
class RequiredForPublishedProduct implements ValidationRule
{
    public function __construct(
        private readonly string $status,
        private readonly string $publishedValue = 'published',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->status !== $this->publishedValue) {
            return;
        }

        foreach (['hy', 'ru', 'en'] as $locale) {
            if (trim((string) ($value[$locale] ?? '')) === '') {
                $fail("The {$attribute}.{$locale} field is required to publish.");

                return;
            }
        }
    }
}
