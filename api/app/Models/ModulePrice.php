<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The monthly price (AMD) of one module, added to the plan's when it is on. */
class ModulePrice extends Model
{
    protected $fillable = ['feature_key', 'price'];

    protected $casts = ['price' => 'integer'];

    /** @return array<string, int> feature key => price */
    public static function map(): array
    {
        return static::query()->pluck('price', 'feature_key')->map(fn ($price) => (int) $price)->all();
    }
}
