<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSlugRedirect extends Model
{
    protected $fillable = [
        'locale',
        'old_slug',
        'product_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
