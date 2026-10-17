<?php

namespace App\Models;

use App\Enums\ContactChannelType;
use Illuminate\Database\Eloquent\Model;

class ContactChannel extends Model
{
    protected $fillable = [
        'type',
        'value',
        'label',
        'is_primary',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'type' => ContactChannelType::class,
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
