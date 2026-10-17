<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasTranslations;

    protected $fillable = [
        'address',
    ];

    protected $casts = [
        'address' => 'array',
    ];

    // Contact info is site-wide, not per-record — callers always want this
    // one row rather than querying by id.
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
