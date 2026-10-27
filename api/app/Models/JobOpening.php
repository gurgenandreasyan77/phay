<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A job the business is hiring for, with its posting text. */
class JobOpening extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['open', 'paused', 'closed'];

    public const EMPLOYMENT = ['full_time', 'part_time', 'shift', 'freelance'];

    protected $fillable = ['title', 'description', 'salary', 'employment', 'status'];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
