<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Team\CandidateStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who may work for the business: where they are on the way, their interview. */
class Candidate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['job_opening_id', 'name', 'phone', 'email', 'source', 'stage', 'interview_at', 'rating', 'notes', 'employee_id'];

    protected $casts = [
        'stage' => CandidateStage::class,
        'job_opening_id' => 'integer',
        'employee_id' => 'integer',
        'rating' => 'integer',
    ];

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** "2026-10-30 15:00" in the business's local time, as stored. */
    public function interviewTime(): ?string
    {
        $raw = $this->getRawOriginal('interview_at') ?? $this->interview_at;

        return $raw ? substr((string) $raw, 0, 16) : null;
    }
}
