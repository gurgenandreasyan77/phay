<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A business's AI assistant use in one subscription month (period_start:
 * the day that month started, see App\Assistant\AssistantQuota).
 */
class AssistantUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['period_start', 'requests', 'bonus_used', 'input_tokens', 'output_tokens'];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'requests' => 'integer',
        'bonus_used' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];
}
