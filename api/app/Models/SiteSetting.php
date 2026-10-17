<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use BelongsToTenant, HasTranslations;

    protected $fillable = [
        'address',
    ];

    protected $casts = [
        'address' => 'array',
    ];

    // Contact info is per business, not per record — callers always want
    // the current business's one row (BelongsToTenant scopes and fills it).
    public static function current(): self
    {
        return static::query()->firstOrCreate();
    }
}
