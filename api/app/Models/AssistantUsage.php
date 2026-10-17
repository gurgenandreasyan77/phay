<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AssistantUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['month', 'requests', 'input_tokens', 'output_tokens'];

    protected $casts = [
        'requests' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public static function forCurrentMonth(): self
    {
        return static::query()->firstOrCreate(['month' => now()->format('Y-m')]);
    }
}
