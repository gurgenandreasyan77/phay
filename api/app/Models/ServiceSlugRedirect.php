<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSlugRedirect extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'old_slug',
        'service_id',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
