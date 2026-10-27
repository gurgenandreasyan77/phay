<?php

namespace App\Team;

use App\Assistant\ReplyActions;
use App\Enums\BusinessRole;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeGroup;
use App\Models\JobOpening;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The HR agent's work: reading the team (employees, who is away, the jobs
 * and their candidates) and preparing changes of it: an absence added or
 * cancelled, an employee added or changed, a job opening, a candidate, a
 * hire with its onboarding checklist. Every change is only planned
 * (HrChanges) and comes back to the chat as a card the owner applies.
 *
 * Only for those who manage the team (owner, admin). Phones and emails of
 * candidates and employees are never read back to the model; it may write
 * them when the owner gives them.
 */
class HrTools
{
    public const TEAM = 'team_overview';

    public const ABSENCES = 'list_absences';

    public const HIRING = 'list_hiring';

    public const ADD_ABSENCE = 'add_absence';

    public const CANCEL_ABSENCE = 'cancel_absence';

    public const SAVE_EMPLOYEE = 'save_employee';

    public const SAVE_OPENING = 'save_job_opening';

    public const SAVE_CANDIDATE = 'save_candidate';

    public const HIRE = 'hire_candidate';

    public const ONBOARDING = 'set_onboarding';

    public const NAMES = [self::TEAM, self::ABSENCES, self::HIRING, self::ADD_ABSENCE, self::CANCEL_ABSENCE, self::SAVE_EMPLOYEE, self::SAVE_OPENING, self::SAVE_CANDIDATE, self::HIRE, self::ONBOARDING];

    public function __construct(
        private readonly Team $team,
        private readonly HrChanges $changes,
        private readonly ReplyActions $actions,
        private readonly TenantContext $tenants,
    ) {}

    public function available(): bool
    {
        return (bool) auth()->user()?->businessRole($this->tenants->current())?->can(BusinessRole::SETTINGS);
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $date = fn (string $what) => ['type' => 'string', 'description' => "{$what}, YYYY-MM-DD."];
        $employee = ['type' => 'string', 'description' => 'The employee: their id or name.'];
        $text = fn (string $what = '') => array_filter(['type' => 'string', 'description' => $what ?: null]);
        $object = fn (array $properties, array $required = []) => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];

        return [
            [
                'name' => self::TEAM,
                'description' => 'The team: every employee with id, position, group, whether still working, first working day, birthday, onboarding checklist progress, their current or next absence and vacation days this year; and the groups.',
                'inputSchema' => ['type' => 'object'],
            ],
            [
                'name' => self::ABSENCES,
                'description' => 'Absences (vacations, days off, sick leave...) touching a range of days, with ids, and for each the orders the employee has on those days.',
                'inputSchema' => $object(['from' => $date('First day'), 'to' => $date('Last day')], ['from', 'to']),
            ],
            [
                'name' => self::HIRING,
                'description' => 'Job openings (with their posting text) and candidates: stage, source, interview time, rating, notes (no phones or emails).',
                'inputSchema' => ['type' => 'object'],
            ],
            [
                'name' => self::ADD_ABSENCE,
                'description' => 'Prepares an absence: a vacation, a day off, sick leave, unpaid leave. It shows in the calendar and the team is reminded in the bell. Tells you the employee\'s orders on those days, to reassign.',
                'inputSchema' => $object([
                    'employee' => $employee,
                    'kind' => ['type' => 'string', 'enum' => AbsenceKind::values()],
                    'from' => $date('First day away'),
                    'to' => $date('Last day away (same as from for one day)'),
                    'note' => $text(),
                ], ['employee', 'kind', 'from', 'to']),
            ],
            [
                'name' => self::CANCEL_ABSENCE,
                'description' => 'Prepares cancelling an absence by its id (list_absences).',
                'inputSchema' => $object(['id' => ['type' => 'integer']], ['id']),
            ],
            [
                'name' => self::SAVE_EMPLOYEE,
                'description' => 'Prepares adding an employee (no id) or changing one (id): name, position, phone, email, group (name), first working day, birthday, or active=false when they left.',
                'inputSchema' => $object([
                    'id' => ['type' => 'integer'],
                    'name' => $text(), 'position' => $text(), 'phone' => $text(), 'email' => $text(),
                    'group' => $text('Name of an existing group.'),
                    'hired_on' => $date('First working day'),
                    'birth_date' => $date('Birthday'),
                    'active' => ['type' => 'boolean'],
                ]),
            ],
            [
                'name' => self::SAVE_OPENING,
                'description' => 'Prepares a new job opening (no id) or changes one (id): title, the posting text (duties, requirements, conditions, how to apply), salary, employment, status (open, paused, closed).',
                'inputSchema' => $object([
                    'id' => ['type' => 'integer'],
                    'title' => $text(),
                    'description' => $text('The posting, ready to publish.'),
                    'salary' => $text('e.g. "250 000 – 300 000 դր." or "% from orders"'),
                    'employment' => ['type' => 'string', 'enum' => JobOpening::EMPLOYMENT],
                    'status' => ['type' => 'string', 'enum' => JobOpening::STATUSES],
                ]),
            ],
            [
                'name' => self::SAVE_CANDIDATE,
                'description' => 'Prepares a new candidate (no id) or changes one (id): name, contacts the owner gave, opening, source, stage, interview time, rating 1-5, notes (e.g. from a CV the owner attached).',
                'inputSchema' => $object([
                    'id' => ['type' => 'integer'],
                    'opening' => ['type' => 'integer', 'description' => 'Id of the job opening.'],
                    'name' => $text(), 'phone' => $text(), 'email' => $text(), 'source' => $text('staff.am, Instagram, a friend...'),
                    'stage' => ['type' => 'string', 'enum' => CandidateStage::values()],
                    'interview_at' => $text('YYYY-MM-DD HH:MM, the business\'s local time.'),
                    'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'notes' => $text(),
                ]),
            ],
            [
                'name' => self::HIRE,
                'description' => 'Prepares hiring a candidate: they become an employee (with their contacts, position and first working day) with an onboarding checklist, and the candidate is marked hired.',
                'inputSchema' => $object([
                    'candidate' => ['type' => 'integer'],
                    'position' => $text(),
                    'hired_on' => $date('First working day'),
                    'onboarding' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Checklist items; leave out for the usual ones.'],
                ], ['candidate']),
            ],
            [
                'name' => self::ONBOARDING,
                'description' => 'Prepares an employee\'s onboarding checklist: the whole list, each item done or not.',
                'inputSchema' => $object([
                    'employee' => $employee,
                    'items' => ['type' => 'array', 'items' => $object(['title' => $text(), 'done' => ['type' => 'boolean']], ['title'])],
                ], ['employee', 'items']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function run(string $name, array $input): array
    {
        if (! $this->available()) {
            throw new InvalidArgumentException('Only the owner or an admin of the business may do this.');
        }

        return match ($name) {
            self::TEAM => $this->overview(),
            self::ABSENCES => $this->absences($input),
            self::HIRING => $this->hiring(),
            default => $this->card(...$this->plan($name, $input)),
        };
    }

    /** @return array{0: string, 1: list<array|null>, 2: array<string, mixed>} title, operations, what to tell the model */
    private function plan(string $name, array $input): array
    {
        return match ($name) {
            self::ADD_ABSENCE => $this->addAbsence($input),
            self::CANCEL_ABSENCE => $this->cancelAbsence($input),
            self::SAVE_EMPLOYEE => $this->saveEmployee($input),
            self::SAVE_OPENING => $this->saveOpening($input),
            self::SAVE_CANDIDATE => $this->saveCandidate($input),
            self::HIRE => $this->hire($input),
            self::ONBOARDING => $this->setOnboarding($input),
            default => throw new InvalidArgumentException("Unknown tool [{$name}]."),
        };
    }

    private function card(string $title, array $ops, array $extra = []): array
    {
        $change = $this->changes->plan($title, $ops, auth()->user());
        $this->actions->add(['type' => 'hr', 'id' => $change->id]);
        $card = $this->changes->describe($change);

        return [
            'planned' => true,
            'note' => 'Nothing is changed yet: the owner sees this as a card under your answer and applies it with «Կիրառել» (or not). It can be undone afterwards.',
            'card' => ['title' => $card['title'], 'lines' => $card['lines']],
            ...$extra,
        ];
    }

    private function overview(): array
    {
        $today = $this->team->today()->toDateString();
        $absences = EmployeeAbsence::query()->where('ends_on', '>=', $today)->orderBy('starts_on')->get()->groupBy('employee_id');
        $vacation = $this->team->vacationDays((int) substr($today, 0, 4));

        return [
            'today' => $today,
            'employees' => Employee::query()->with('group:id,name')->orderByDesc('is_active')->orderBy('name')->get()->map(fn (Employee $e) => array_filter([
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
                'group' => $e->group?->name,
                'active' => $e->is_active,
                'hiredOn' => $e->hired_on?->toDateString(),
                'birthday' => $e->birth_date?->format('m-d'),
                'onboarding' => $e->onboarding ? collect(Team::onboarding($e))->map(fn ($i) => ($i['done'] ? '[x] ' : '[ ] ').$i['title'])->all() : null,
                'away' => ($absences[$e->id] ?? collect())->take(3)->map(fn (EmployeeAbsence $a) => Team::absence($a))->map(fn ($a) => "#{$a['id']} {$a['kind']} {$a['from']}..{$a['to']}")->values()->all() ?: null,
                'vacationDaysThisYear' => $vacation[$e->id] ?? null,
            ], fn ($v) => $v !== null && $v !== []))->values()->all(),
            'groups' => EmployeeGroup::query()->with('manager:id,name')->orderBy('name')->get()->map(fn (EmployeeGroup $g) => array_filter(['name' => $g->name, 'manager' => $g->manager?->name]))->all(),
        ];
    }

    private function absences(array $input): array
    {
        [$from, $to] = [$this->date($input['from'] ?? null, 'from'), $this->date($input['to'] ?? null, 'to')];
        $list = $this->team->absences($from, $to);

        return [
            'absences' => $list->map(fn (EmployeeAbsence $a) => [
                ...Team::absence($a),
                'ordersOnThoseDays' => $a->employee ? array_sum(array_column($this->team->ordersOn($a->employee, $a->starts_on->toDateString(), $a->ends_on->toDateString()), 'count')) : 0,
            ])->values()->all(),
        ];
    }

    private function hiring(): array
    {
        $candidates = $this->team->candidates()->groupBy('job_opening_id');

        return [
            'openings' => $this->team->openings()->map(fn (JobOpening $o) => [
                ...Team::opening($o),
                'candidates' => ($candidates[$o->id] ?? collect())->map(fn (Candidate $c) => Team::candidate($c, false))->values()->all(),
            ])->all(),
            'withoutOpening' => ($candidates[''] ?? collect())->map(fn (Candidate $c) => Team::candidate($c, false))->values()->all(),
        ];
    }

    private function addAbsence(array $input): array
    {
        $employee = $this->employee($input['employee'] ?? null);
        $kind = AbsenceKind::tryFrom((string) ($input['kind'] ?? '')) ?? throw new InvalidArgumentException('kind must be one of '.implode(', ', AbsenceKind::values()).'.');
        $from = $this->date($input['from'] ?? null, 'from');
        $to = $this->date($input['to'] ?? null, 'to');
        if ($to < $from) {
            throw new HrRefused('Վերջին օրը չի կարող լինել առաջինից առաջ։');
        }
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            throw new HrRefused('Բացակայությունը չի կարող լինել մեկ տարուց ավելի։');
        }
        $overlap = EmployeeAbsence::query()->where('employee_id', $employee->id)->overlapping($from, $to)->first();
        if ($overlap) {
            throw new HrRefused("{$employee->name}-ն այդ օրերին արդեն բացակա է ({$overlap->kind->label()}, ".self::dates($overlap->starts_on->toDateString(), $overlap->ends_on->toDateString()).')։');
        }

        $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        $orders = $this->team->ordersOn($employee, $from, $to);
        $count = array_sum(array_column($orders, 'count'));
        $lines = ["{$employee->name}՝ ".mb_strtolower($kind->label()).', '.self::dates($from, $to)." ({$days} օր)"];
        if (filled($input['note'] ?? null)) {
            $lines[] = 'Նշում՝ '.trim((string) $input['note']);
        }
        if ($count > 0) {
            $lines[] = "Ուշադրություն՝ այդ օրերին {$employee->name}-ի անունով {$count} պատվեր կա. փոխանցեք ուրիշին։";
        }

        return [
            "{$kind->label()}՝ {$employee->name}",
            [HrChanges::create('absence', [
                'employee_id' => $employee->id,
                'kind' => $kind->value,
                'starts_on' => $from,
                'ends_on' => $to,
                'note' => filled($input['note'] ?? null) ? mb_substr(trim((string) $input['note']), 0, 500) : null,
                'created_by' => auth()->id(),
            ], $lines)],
            ['ordersOnThoseDays' => $orders],
        ];
    }

    private function cancelAbsence(array $input): array
    {
        $absence = EmployeeAbsence::query()->with('employee')->find((int) ($input['id'] ?? 0)) ?? throw new InvalidArgumentException('No such absence (see list_absences).');
        $name = $absence->employee?->name ?? '—';

        return [
            "Չեղարկել բացակայությունը՝ {$name}",
            [HrChanges::delete($absence, 'absence', "{$name}՝ ".mb_strtolower($absence->kind->label()).', '.self::dates($absence->starts_on->toDateString(), $absence->ends_on->toDateString()).'՝ կհեռացվի')],
            [],
        ];
    }

    private function saveEmployee(array $input): array
    {
        $values = [];
        foreach (['name', 'position', 'phone', 'email'] as $field) {
            if (array_key_exists($field, $input)) {
                $values[$field] = filled($input[$field]) ? mb_substr(preg_replace('/\s+/u', ' ', trim((string) $input[$field])), 0, $field === 'email' ? 150 : 100) : null;
            }
        }
        foreach (['hired_on', 'birth_date'] as $field) {
            if (array_key_exists($field, $input)) {
                $values[$field] = filled($input[$field]) ? $this->date($input[$field], $field) : null;
            }
        }
        if (array_key_exists('active', $input)) {
            $values['is_active'] = (bool) $input['active'];
        }
        if (filled($input['group'] ?? null)) {
            $group = EmployeeGroup::query()->get()->first(fn (EmployeeGroup $g) => mb_strtolower($g->name) === mb_strtolower(trim((string) $input['group'])))
                ?? throw new HrRefused('«'.$input['group'].'» խումբ չկա։ Կան՝ '.(EmployeeGroup::query()->pluck('name')->implode(', ') ?: 'ոչ մի').'։');
            $values['group_id'] = $group->id;
        }

        $current = isset($input['id']) ? (Employee::query()->find((int) $input['id']) ?? throw new InvalidArgumentException('No such employee (see team_overview).')) : null;
        $name = $values['name'] ?? $current?->name;
        if (blank($name)) {
            throw new HrRefused('Նշեք աշխատակցի անունը։');
        }
        $taken = Employee::query()->when($current, fn ($q) => $q->whereKeyNot($current->id))->get(['id', 'name'])
            ->contains(fn (Employee $e) => Employee::normalize($e->name) === Employee::normalize($name));
        if ($taken) {
            throw new HrRefused("«{$name}» անունով աշխատակից արդեն կա։");
        }

        if (! $current) {
            return ["Նոր աշխատակից՝ {$name}", [HrChanges::create('employee', ['is_active' => true, ...$values], $this->employeeLines(null, $values))], []];
        }

        $op = HrChanges::update($current, 'employee', $values, '');
        if (! $op) {
            throw new HrRefused('Ոչինչ չի փոխվում։');
        }
        $op['line'] = $this->employeeLines($op['before'], $op['after']);

        return ["{$current->name}՝ տվյալների փոփոխություն", [$op], []];
    }

    /** @return list<string> */
    private function employeeLines(?array $before, array $after): array
    {
        $labels = ['name' => 'Անուն', 'position' => 'Պաշտոն', 'phone' => 'Հեռախոս', 'email' => 'Էլ. փոստ', 'hired_on' => 'Աշխատանքի առաջին օր', 'birth_date' => 'Ծննդյան օր', 'group_id' => 'Խումբ', 'is_active' => 'Կարգավիճակ'];
        $show = function (string $field, mixed $value): string {
            return match (true) {
                $value === null => '—',
                $field === 'is_active' => $value ? 'աշխատում է' : 'այլևս չի աշխատում',
                $field === 'group_id' => EmployeeGroup::query()->find($value)?->name ?? '—',
                in_array($field, ['hired_on', 'birth_date'], true) => self::day((string) $value),
                default => (string) $value,
            };
        };

        $lines = [];
        foreach ($after as $field => $value) {
            if (! isset($labels[$field]) || ($before === null && ($value === null || $field === 'is_active'))) {
                continue;
            }
            $lines[] = $labels[$field].'՝ '.($before !== null ? $show($field, $before[$field] ?? null).' → ' : '').$show($field, $value);
        }

        return $lines;
    }

    private function saveOpening(array $input): array
    {
        $values = [];
        foreach (['title' => 150, 'salary' => 100, 'description' => 10000] as $field => $max) {
            if (array_key_exists($field, $input)) {
                $values[$field] = filled($input[$field]) ? mb_substr(trim((string) $input[$field]), 0, $max) : null;
            }
        }
        foreach (['employment' => JobOpening::EMPLOYMENT, 'status' => JobOpening::STATUSES] as $field => $allowed) {
            if (array_key_exists($field, $input)) {
                if ($input[$field] !== null && ! in_array($input[$field], $allowed, true)) {
                    throw new InvalidArgumentException("{$field} must be one of ".implode(', ', $allowed).'.');
                }
                $values[$field] = $input[$field];
            }
        }

        $current = isset($input['id']) ? (JobOpening::query()->find((int) $input['id']) ?? throw new InvalidArgumentException('No such opening (see list_hiring).')) : null;
        $lines = fn (array $v) => array_values(array_filter([
            isset($v['title']) ? 'Պաշտոն՝ '.$v['title'] : null,
            isset($v['salary']) ? 'Աշխատավարձ՝ '.$v['salary'] : null,
            isset($v['employment']) ? 'Զբաղվածություն՝ '.self::employment($v['employment']) : null,
            isset($v['status']) ? 'Կարգավիճակ՝ '.self::openingStatus($v['status']) : null,
            isset($v['description']) ? "Հայտարարության տեքստ՝\n".$v['description'] : null,
        ]));

        if (! $current) {
            if (blank($values['title'] ?? null)) {
                throw new HrRefused('Նշեք պաշտոնի անվանումը։');
            }

            return ["Նոր թափուր հաստիք՝ {$values['title']}", [HrChanges::create('opening', ['status' => 'open', ...$values], $lines($values))], []];
        }

        $op = HrChanges::update($current, 'opening', $values, '') ?? throw new HrRefused('Ոչինչ չի փոխվում։');
        $op['line'] = $lines($op['after']);

        return ["Թափուր հաստիք՝ {$current->title}", [$op], []];
    }

    private function saveCandidate(array $input): array
    {
        $values = [];
        foreach (['name' => 100, 'phone' => 50, 'email' => 150, 'source' => 100, 'notes' => 5000] as $field => $max) {
            if (array_key_exists($field, $input)) {
                $values[$field] = filled($input[$field]) ? mb_substr(trim((string) $input[$field]), 0, $max) : null;
            }
        }
        if (array_key_exists('opening', $input)) {
            $values['job_opening_id'] = $input['opening'] === null ? null
                : (JobOpening::query()->find((int) $input['opening'])?->id ?? throw new InvalidArgumentException('No such opening (see list_hiring).'));
        }
        if (array_key_exists('stage', $input)) {
            $values['stage'] = (CandidateStage::tryFrom((string) $input['stage']) ?? throw new InvalidArgumentException('Unknown stage.'))->value;
        }
        if (array_key_exists('interview_at', $input)) {
            $at = trim((string) $input['interview_at']);
            if ($at !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $at)) {
                throw new InvalidArgumentException('interview_at must be YYYY-MM-DD HH:MM.');
            }
            $values['interview_at'] = $at === '' ? null : str_replace('T', ' ', $at).':00';
        }
        if (array_key_exists('rating', $input)) {
            $values['rating'] = $input['rating'] === null ? null : max(1, min(5, (int) $input['rating']));
        }

        $current = isset($input['id']) ? (Candidate::query()->find((int) $input['id']) ?? throw new InvalidArgumentException('No such candidate (see list_hiring).')) : null;
        $lines = function (array $v): array {
            return array_values(array_filter([
                isset($v['name']) ? 'Անուն՝ '.$v['name'] : null,
                array_key_exists('job_opening_id', $v) ? 'Հաստիք՝ '.(JobOpening::query()->find($v['job_opening_id'])?->title ?? '—') : null,
                isset($v['stage']) ? 'Փուլ՝ '.CandidateStage::from($v['stage'])->label() : null,
                array_key_exists('interview_at', $v) ? 'Հարցազրույց՝ '.($v['interview_at'] ? self::day(substr($v['interview_at'], 0, 10)).' '.substr($v['interview_at'], 11, 5) : 'չեղարկված') : null,
                isset($v['phone']) || isset($v['email']) ? 'Կոնտակտներ՝ '.implode(', ', array_filter([$v['phone'] ?? null, $v['email'] ?? null])) : null,
                isset($v['source']) ? 'Աղբյուր՝ '.$v['source'] : null,
                isset($v['rating']) ? 'Գնահատական՝ '.$v['rating'].'/5' : null,
                isset($v['notes']) ? 'Նշումներ՝ '.$v['notes'] : null,
            ]));
        };

        if (! $current) {
            if (blank($values['name'] ?? null)) {
                throw new HrRefused('Նշեք թեկնածուի անունը։');
            }

            return ["Նոր թեկնածու՝ {$values['name']}", [HrChanges::create('candidate', ['stage' => CandidateStage::New->value, ...$values], $lines($values))], []];
        }

        $op = HrChanges::update($current, 'candidate', $values, '') ?? throw new HrRefused('Ոչինչ չի փոխվում։');
        $op['line'] = $lines($op['after']);

        return ["Թեկնածու՝ {$current->name}", [$op], []];
    }

    private function hire(array $input): array
    {
        $candidate = Candidate::query()->find((int) ($input['candidate'] ?? 0)) ?? throw new InvalidArgumentException('No such candidate (see list_hiring).');
        if ($candidate->stage === CandidateStage::Hired && $candidate->employee_id) {
            throw new HrRefused("{$candidate->name}-ն արդեն ընդունված է։");
        }
        if (Employee::query()->get(['id', 'name'])->contains(fn (Employee $e) => Employee::normalize($e->name) === Employee::normalize($candidate->name))) {
            throw new HrRefused("«{$candidate->name}» անունով աշխատակից արդեն կա։ Նախ փոխեք անունը։");
        }

        $position = filled($input['position'] ?? null) ? mb_substr(trim((string) $input['position']), 0, 100) : $candidate->opening?->title;
        $hiredOn = filled($input['hired_on'] ?? null) ? $this->date($input['hired_on'], 'hired_on') : null;
        $items = array_values(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 200), (array) ($input['onboarding'] ?? Team::defaultOnboarding())), 'strlen'));
        $onboarding = array_map(fn (string $t) => ['title' => $t, 'done' => false], array_slice($items, 0, 30));

        return [
            "Ընդունել աշխատանքի՝ {$candidate->name}",
            [
                HrChanges::create('employee', [
                    'name' => $candidate->name, 'phone' => $candidate->phone, 'email' => $candidate->email, 'position' => $position,
                    'hired_on' => $hiredOn, 'onboarding' => $onboarding, 'is_active' => true,
                ], array_values(array_filter([
                    "Նոր աշխատակից՝ {$candidate->name}".($position ? ", {$position}" : ''),
                    $hiredOn ? 'Աշխատանքի առաջին օր՝ '.self::day($hiredOn) : null,
                    $onboarding ? 'Ադապտացիայի ցանկ՝ '.implode('; ', array_column($onboarding, 'title')) : null,
                ]))),
                HrChanges::update($candidate, 'candidate', ['stage' => CandidateStage::Hired->value], 'Թեկնածուի փուլ՝ '.CandidateStage::Hired->label(), ['employee_id' => 0]),
            ],
            [],
        ];
    }

    private function setOnboarding(array $input): array
    {
        $employee = $this->employee($input['employee'] ?? null);
        $items = array_values(array_filter(array_map(fn ($i) => [
            'title' => mb_substr(trim((string) ($i['title'] ?? '')), 0, 200),
            'done' => (bool) ($i['done'] ?? false),
        ], array_slice((array) ($input['items'] ?? []), 0, 30)), fn ($i) => $i['title'] !== ''));

        $op = HrChanges::update($employee, 'employee', ['onboarding' => $items ?: null], array_map(fn ($i) => ($i['done'] ? '✓ ' : '○ ').$i['title'], $items) ?: ['Ցանկը կհեռացվի'])
            ?? throw new HrRefused('Ցանկում ոչինչ չի փոխվում։');

        return ["{$employee->name}՝ ադապտացիայի ցանկ", [$op], []];
    }

    private function employee(mixed $ref): Employee
    {
        $ref = trim((string) $ref);
        $employee = ctype_digit($ref) ? Employee::query()->find((int) $ref) : null;
        $employee ??= Employee::query()->get()->first(fn (Employee $e) => Employee::normalize($e->name) === Employee::normalize($ref));
        if (! $employee && $ref !== '') {
            // A first name alone, when it is only one person's.
            $like = Employee::query()->get()->filter(fn (Employee $e) => str_contains(Employee::normalize($e->name), Employee::normalize($ref)));
            $employee = $like->count() === 1 ? $like->first() : null;
        }

        return $employee ?? throw new InvalidArgumentException("No employee \"{$ref}\" (see team_overview).");
    }

    private function date(mixed $value, string $field): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            throw new InvalidArgumentException("{$field} must be a date YYYY-MM-DD.");
        }

        return $value;
    }

    /** "2026-11-01" → "01.11.2026" */
    public static function day(string $date): string
    {
        return substr($date, 8, 2).'.'.substr($date, 5, 2).'.'.substr($date, 0, 4);
    }

    public static function dates(string $from, string $to): string
    {
        return $from === $to ? self::day($from) : self::day($from).'–'.self::day($to);
    }

    public static function employment(?string $kind): string
    {
        return match ($kind) {
            'full_time' => 'լրիվ դրույք',
            'part_time' => 'կես դրույք',
            'shift' => 'հերթափոխով',
            'freelance' => 'ազատ գրաֆիկ / պայմանագրային',
            default => '—',
        };
    }

    public static function openingStatus(?string $status): string
    {
        return match ($status) {
            'open' => 'բաց',
            'paused' => 'դադարեցված',
            'closed' => 'փակված',
            default => '—',
        };
    }
}
