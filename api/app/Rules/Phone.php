<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts Armenian numbers (+374XXXXXXXX or 0XXXXXXXX) and general
 * international E.164-style numbers, tolerating spaces/dashes/parentheses.
 */
class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = preg_replace('/[\s\-()]/', '', (string) $value);

        $isArmenian = (bool) preg_match('/^(\+374|0)\d{8}$/', $normalized);
        $isInternational = (bool) preg_match('/^\+[1-9]\d{7,14}$/', $normalized);

        if (! $isArmenian && ! $isInternational) {
            $fail('validation.custom.phone.phone')->translate();
        }
    }
}
