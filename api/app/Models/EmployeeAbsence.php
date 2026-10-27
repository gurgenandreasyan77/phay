<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Team\AbsenceKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee away for some days: a vacation, a day off, sick leave... (dates inclusive). */
class EmployeeAbsence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['employee_id', 'kind', 'starts_on', 'ends_on', 'note', 'created_by'];

    protected $casts = [
        'kind' => AbsenceKind::class,
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'employee_id' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Absences that touch the days from..to (inclusive). */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        // Not "<= $to": a date may be stored with a time (SQLite), and the last day counts.
        return $query->where('starts_on', '<', CarbonImmutable::parse($to)->addDay()->toDateString())->where('ends_on', '>=', $from);
    }

    /** How many days it lasts. */
    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
