<?php

namespace App\Tenancy;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules limited to the current tenant. The plain "unique:" and
 * "exists:" strings query the whole table, which would let one business
 * see another's slugs or attach another business's records by id.
 */
class TenantRule
{
    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return (new Unique($table, $column))->where('tenant_id', app(TenantContext::class)->id());
    }

    public static function exists(string $table, string $column = 'NULL'): Exists
    {
        return (new Exists($table, $column))->where('tenant_id', app(TenantContext::class)->id());
    }
}
