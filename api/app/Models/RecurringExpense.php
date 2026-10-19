<?php

namespace App\Models;

use App\Enums\TablePeriod;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An expense that repeats: rent every month from 1 March, taxes every
 * quarter. It is due on starts_on and then every week, month, quarter or
 * year after it, until ends_on (if set); the figures count each due date,
 * nothing is stored per payment.
 */
class RecurringExpense extends Model
{
    use BelongsToTenant;

    /** How often one can repeat. */
    public const FREQUENCIES = [TablePeriod::Week, TablePeriod::Month, TablePeriod::Quarter, TablePeriod::Year];

    protected $fillable = ['expense_category_id', 'name', 'amount', 'frequency', 'starts_on', 'ends_on', 'note'];

    protected $casts = [
        'expense_category_id' => 'integer',
        'amount' => 'decimal:2',
        'frequency' => TablePeriod::class,
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * The due dates between two days, both included: the 31st of a month
     * stays the last day of shorter months.
     *
     * @return list<CarbonImmutable>
     */
    public function dueDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $this->starts_on;
        $last = $this->ends_on !== null && $this->ends_on < $to ? $this->ends_on : $to;
        $dates = [];

        for ($n = 0; ($date = $this->nth($start, $n)) <= $last; $n++) {
            if ($date >= $from) {
                $dates[] = $date;
            }
            // A weekly expense over years: at most a few hundred steps.
            if ($n > 5000) {
                break;
            }
        }

        return $dates;
    }

    /** The amount as a number (2 decimals at most). */
    public function value(): int|float
    {
        $amount = (float) $this->amount;

        return floor($amount) == $amount ? (int) $amount : $amount;
    }

    /** How much a month it costs, to compare expenses of different frequencies. */
    public function monthly(): float
    {
        return round(match ($this->frequency) {
            TablePeriod::Week => $this->value() * 52 / 12,
            TablePeriod::Quarter => $this->value() / 3,
            TablePeriod::Year => $this->value() / 12,
            default => $this->value(),
        }, 2);
    }

    private function nth(CarbonImmutable $start, int $n): CarbonImmutable
    {
        return match ($this->frequency) {
            TablePeriod::Week => $start->addWeeks($n),
            TablePeriod::Quarter => $start->addMonthsNoOverflow(3 * $n),
            TablePeriod::Year => $start->addYearsNoOverflow($n),
            default => $start->addMonthsNoOverflow($n),
        };
    }
}
