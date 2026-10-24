<?php

namespace App\Models;

use App\Enums\VerificationPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A 6-digit code sent to confirm an email or a phone; stored hashed. */
class VerificationCode extends Model
{
    protected $fillable = ['user_id', 'purpose', 'destination', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];

    protected $casts = [
        'purpose' => VerificationPurpose::class,
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
