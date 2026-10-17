<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSlugRedirect extends Model
{
    protected $fillable = [
        'old_slug',
        'service_id',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
