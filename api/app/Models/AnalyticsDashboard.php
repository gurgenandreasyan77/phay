<?php

namespace App\Models;

use App\Enums\DataTableSection;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The widgets a business chose for the analytics screen of one section
 * (see DashboardWidget). No row: the defaults for its data.
 */
class AnalyticsDashboard extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section', 'widgets'];

    protected $casts = [
        'section' => DataTableSection::class,
        'widgets' => 'array',
    ];
}
