<?php

namespace App\Team;

use App\Models\Candidate;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\JobOpening;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The business's team as the admin and the HR agent read it: who is away
 * when, the jobs it hires for and their candidates, and what new employees
 * still have to go through. Everything inside the current business.
 */
class Team
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tenants->current()->timezone ?: config('app.timezone'))->startOfDay();
    }

    /** @return Collection<int, EmployeeAbsence> absences touching from..to, by start */
    public function absences(string $from, string $to): Collection
    {
        return EmployeeAbsence::query()->with('employee:id,name,position')
            ->overlapping($from, $to)
            ->orderBy('starts_on')->orderBy('id')
            ->limit(1000)
            ->get();
    }

    /** @return array<string, mixed> */
    public static function absence(EmployeeAbsence $a): array
    {
        return [
            'id' => $a->id,
            'employee' => $a->employee ? ['id' => $a->employee->id, 'name' => $a->employee->name] : null,
            'kind' => $a->kind->value,
            'kindLabel' => $a->kind->label(),
            'from' => $a->starts_on->toDateString(),
            'to' => $a->ends_on->toDateString(),
            'days' => $a->days(),
            'note' => $a->note,
        ];
    }

    /**
     * The employee's orders on those days (rows of the tables linked to
     * them): what has to move to someone else if they are away.
     *
     * @return list<array{date: string, count: int}>
     */
    public function ordersOn(Employee $employee, string $from, string $to): array
    {
        return DataTableRow::query()->counted()
            ->where('employee_id', $employee->id)
            ->where('occurred_at', '>=', $from.' 00:00:00')
            ->where('occurred_at', '<=', $to.' 23:59:59')
            ->get(['id', 'occurred_at'])
            ->groupBy(fn (DataTableRow $r) => substr((string) $r->getRawOriginal('occurred_at'), 0, 10))
            ->map(fn (Collection $rows, string $date) => ['date' => $date, 'count' => $rows->count()])
            ->sortKeys()
            ->values()
            ->all();
    }

    /** Days of vacation each employee took or has planned in the year. @return array<int, int> */
    public function vacationDays(int $year): array
    {
        $from = "{$year}-01-01";
        $to = "{$year}-12-31";

        return EmployeeAbsence::query()->overlapping($from, $to)->where('kind', AbsenceKind::Vacation->value)->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $list) => $list->sum(function (EmployeeAbsence $a) use ($from, $to) {
                $start = max($a->starts_on->toDateString(), $from);
                $end = min($a->ends_on->toDateString(), $to);

                return (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
            }))
            ->all();
    }

    /** @return Collection<int, JobOpening> open ones first */
    public function openings(): Collection
    {
        return JobOpening::query()
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, Candidate> */
    public function candidates(): Collection
    {
        return Candidate::query()->orderByDesc('updated_at')->orderByDesc('id')->limit(500)->get();
    }

    /** @return array<string, mixed> */
    public static function opening(JobOpening $o): array
    {
        return [
            'id' => $o->id,
            'title' => $o->title,
            'description' => $o->description,
            'salary' => $o->salary,
            'employment' => $o->employment,
            'status' => $o->status,
            'createdAt' => $o->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  bool  $contacts  phone and email (the admin; never the AI)
     * @return array<string, mixed>
     */
    public static function candidate(Candidate $c, bool $contacts = true): array
    {
        return array_filter([
            'id' => $c->id,
            'openingId' => $c->job_opening_id,
            'name' => $c->name,
            'phone' => $contacts ? $c->phone : null,
            'email' => $contacts ? $c->email : null,
            'hasContacts' => ! $contacts && ($c->phone || $c->email) ? true : null,
            'source' => $c->source,
            'stage' => $c->stage->value,
            'stageLabel' => $c->stage->label(),
            'interviewAt' => $c->interviewTime(),
            'rating' => $c->rating,
            'notes' => $c->notes,
            'employeeId' => $c->employee_id,
            'updatedAt' => $c->updated_at?->toIso8601String(),
        ], fn ($v) => $v !== null);
    }

    /** @return list<array{title: string, done: bool}> */
    public static function onboarding(Employee $e): array
    {
        return array_values(array_map(fn ($item) => ['title' => (string) ($item['title'] ?? ''), 'done' => (bool) ($item['done'] ?? false)], $e->onboarding ?? []));
    }

    /** The usual first steps of a new employee, for a checklist to start from. @return list<string> */
    public static function defaultOnboarding(): array
    {
        return [
            'Աշխատանքային պայմանագիր և անձնական տվյալներ',
            'Ծանոթացում թիմին և աշխատանքի կանոններին',
            'Աշխատանքային գործիքներ և հագուստ',
            'Առաջին պատվերը՝ փորձառու գործընկերոջ հետ',
            'Առաջին շաբաթվա արդյունքների քննարկում',
        ];
    }
}
