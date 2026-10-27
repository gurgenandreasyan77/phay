<?php

namespace App\Team;

use App\DataTables\Performers;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\HrChange;
use App\Models\JobOpening;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes of the team's records (employees, absences, job openings,
 * candidates) the HR agent prepares and the owner applies from a card in
 * the chat. A change is a list of operations, each creating, updating or
 * deleting one record, with exactly the values before and after; a line
 * per operation says it in Armenian. Nothing is written until apply(),
 * which refuses when the records changed since; an applied change is undone
 * while nobody touched what it changed.
 */
class HrChanges
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    /** The records a change may touch and their fields, by kind of value. */
    public const MODELS = [
        'employee' => [Employee::class, [
            'name' => 'text', 'position' => 'text', 'phone' => 'text', 'email' => 'text', 'hired_on' => 'date', 'birth_date' => 'date',
            'onboarding' => 'json', 'group_id' => 'int', 'is_active' => 'bool',
        ]],
        'absence' => [EmployeeAbsence::class, [
            'employee_id' => 'int', 'kind' => 'text', 'starts_on' => 'date', 'ends_on' => 'date', 'note' => 'text', 'created_by' => 'int',
        ]],
        'opening' => [JobOpening::class, [
            'title' => 'text', 'description' => 'text', 'salary' => 'text', 'employment' => 'text', 'status' => 'text',
        ]],
        'candidate' => [Candidate::class, [
            'job_opening_id' => 'int', 'name' => 'text', 'phone' => 'text', 'email' => 'text', 'source' => 'text', 'stage' => 'text',
            'interview_at' => 'datetime', 'rating' => 'int', 'notes' => 'text', 'employee_id' => 'int',
        ]],
    ];

    public function __construct(private readonly TenantContext $tenants) {}

    /** An operation creating a record; `links` take ids from earlier operations ({employee_id: 0}). */
    public static function create(string $model, array $values, string|array $line, array $links = []): array
    {
        return ['action' => self::CREATE, 'model' => $model, 'id' => null, 'before' => null, 'after' => self::only($model, $values), 'links' => $links, 'line' => $line];
    }

    /** An operation changing some fields of a record (only those that differ are kept). */
    public static function update(Model $record, string $model, array $values, string|array $line, array $links = []): ?array
    {
        $now = self::values($model, $record);
        $after = array_filter(self::only($model, $values), fn ($v, $k) => $now[$k] !== $v, ARRAY_FILTER_USE_BOTH);
        if ($after === [] && $links === []) {
            return null;
        }

        return ['action' => self::UPDATE, 'model' => $model, 'id' => $record->getKey(), 'before' => array_intersect_key($now, $after + $links), 'after' => $after, 'links' => $links, 'line' => $line];
    }

    public static function delete(Model $record, string $model, string|array $line): array
    {
        return ['action' => self::DELETE, 'model' => $model, 'id' => $record->getKey(), 'before' => self::values($model, $record), 'after' => null, 'links' => [], 'line' => $line];
    }

    /** @param  list<array|null>  $ops */
    public function plan(string $title, array $ops, ?User $user): HrChange
    {
        $ops = array_values(array_filter($ops));
        if ($ops === []) {
            throw new InvalidArgumentException('Ոչինչ չի փոխվում։');
        }

        return HrChange::create(['user_id' => $user?->id, 'title' => mb_substr($title, 0, 250), 'ops' => $ops, 'status' => HrChange::PLANNED]);
    }

    public function apply(HrChange $change): HrChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if ($change->status !== HrChange::PLANNED) {
                throw new HrRefused(match ($change->status) {
                    HrChange::APPLIED => 'Այս փոփոխությունն արդեն կիրառված է։',
                    HrChange::DISCARDED => 'Այս փոփոխությունը չեղարկված է։',
                    default => 'Այս փոփոխությունը հետ է բերվել։ Խնդրեք նորը։',
                });
            }
            if (($why = $this->stale($change)) !== null) {
                throw new HrRefused($why);
            }

            $made = [];
            foreach ($change->ops as $i => $op) {
                $values = $this->linked($op, $made);
                $record = $op['action'] === self::CREATE ? null : $this->find($op);
                $made[$i] = match ($op['action']) {
                    self::CREATE => $this->write(new (self::MODELS[$op['model']][0]), $op['model'], $values)->getKey(),
                    self::UPDATE => $this->write($record, $op['model'], $values)->getKey(),
                    self::DELETE => $this->remove($record),
                };
            }

            $change->update(['status' => HrChange::APPLIED, 'applied_at' => now(), 'result' => $made]);

            return $change;
        });
    }

    public function undo(HrChange $change): HrChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if (($why = $this->undoBlocked($change)) !== null) {
                throw new HrRefused($why);
            }

            $made = $change->result ?? [];
            foreach (array_reverse($change->ops, true) as $i => $op) {
                $model = $op['model'];
                match ($op['action']) {
                    self::CREATE => $this->remove($this->find(['model' => $model, 'id' => $made[$i]])),
                    self::UPDATE => $this->write($this->find($op), $model, $op['before']),
                    self::DELETE => $made[$i] = $this->write(new (self::MODELS[$model][0]), $model, $op['before'])->getKey(),
                };
            }

            $change->update(['status' => HrChange::UNDONE, 'undone_at' => now(), 'result' => $made]);

            return $change;
        });
    }

    public function discard(HrChange $change): HrChange
    {
        return DB::transaction(function () use ($change) {
            $change = $this->locked($change);
            if ($change->status !== HrChange::PLANNED) {
                throw new HrRefused('Չեղարկել կարելի է միայն դեռ չկիրառված փոփոխությունը։');
            }
            $change->update(['status' => HrChange::DISCARDED]);

            return $change;
        });
    }

    /** Why a planned change can't be applied as shown any more; null when it can. */
    public function stale(HrChange $change): ?string
    {
        foreach ($change->ops as $op) {
            if ($op['action'] === self::CREATE) {
                continue;
            }
            $record = $this->find($op, false);
            if (! $record) {
                return 'Գրառումներից մեկն արդեն ջնջված է։ Խնդրեք նորից։';
            }
            $now = self::values($op['model'], $record);
            if (array_intersect_key($now, $op['before']) != $op['before']) {
                return 'Տվյալներն այդ ընթացքում փոխվել են։ Խնդրեք նորից։';
            }
        }

        return null;
    }

    /** Why an applied change can't be undone; null when it can. */
    public function undoBlocked(HrChange $change): ?string
    {
        if ($change->status !== HrChange::APPLIED) {
            return 'Հետ բերել կարելի է միայն կիրառված փոփոխությունը։';
        }

        $made = $change->result ?? [];
        foreach ($change->ops as $i => $op) {
            if ($op['action'] === self::DELETE) {
                continue;
            }
            $record = $this->find(['model' => $op['model'], 'id' => $op['action'] === self::CREATE ? $made[$i] : $op['id']], false);
            if (! $record) {
                return 'Գրառումներից մեկն արդեն ջնջված է։';
            }
            $after = $this->linked($op, $made);
            if (array_intersect_key(self::values($op['model'], $record), $after) != $after) {
                return 'Տվյալներն այդ պահից նորից են փոխվել։';
            }
        }

        return null;
    }

    /** @return array<string, mixed> the card */
    public function describe(HrChange $change): array
    {
        return [
            'id' => $change->id,
            'status' => $change->status,
            'title' => $change->title,
            'lines' => array_values(array_filter(array_merge(...array_map(fn ($op) => (array) $op['line'], $change->ops)))),
            'deletes' => collect($change->ops)->contains(fn ($op) => $op['action'] === self::DELETE),
            'stale' => $change->status === HrChange::PLANNED ? $this->stale($change) : null,
            'undoBlocked' => $change->status === HrChange::APPLIED ? $this->undoBlocked($change) : null,
            'link' => $this->link($change),
            'appliedAt' => $change->applied_at?->toIso8601String(),
            'undoneAt' => $change->undone_at?->toIso8601String(),
        ];
    }

    /** Where the owner sees the result in the admin. */
    private function link(HrChange $change): string
    {
        $models = array_unique(array_column($change->ops, 'model'));

        return match (true) {
            in_array('absence', $models, true) => '/admin/employees?tab=absences',
            in_array('opening', $models, true), in_array('candidate', $models, true) => '/admin/employees?tab=hiring',
            default => '/admin/employees',
        };
    }

    /**
     * A record's fields as a change keeps them: dates "Y-m-d", times
     * "Y-m-d H:i", numbers, booleans.
     *
     * @return array<string, mixed>
     */
    public static function values(string $model, Model $record): array
    {
        $out = [];
        foreach (self::MODELS[$model][1] as $field => $kind) {
            $out[$field] = self::normal($kind, $record->getAttributes()[$field] ?? null);
        }

        return $out;
    }

    /** @return array<string, mixed> the known fields of `$values`, normalized */
    public static function only(string $model, array $values): array
    {
        $fields = self::MODELS[$model][1];
        $out = [];
        foreach ($values as $field => $value) {
            if (isset($fields[$field])) {
                $out[$field] = self::normal($fields[$field], $value instanceof \BackedEnum ? $value->value : $value);
            }
        }

        return $out;
    }

    private static function normal(string $kind, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $kind === 'bool' ? false : null;
        }

        return match ($kind) {
            'date' => substr((string) ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value), 0, 10),
            'datetime' => substr(str_replace('T', ' ', (string) ($value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : $value)), 0, 16),
            'int' => (int) $value,
            'bool' => (bool) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    private function write(Model $record, string $model, array $values): Model
    {
        $wasName = $record->exists ? $record->getAttribute('name') : null;
        foreach ($values as $field => $value) {
            $record->setAttribute($field, $value);
        }
        $record->save();

        // An employee's orders follow their name (the performer column).
        if ($model === 'employee' && $wasName === null) {
            Performers::link($record);
        } elseif ($model === 'employee' && $wasName !== $record->getAttribute('name')) {
            Performers::rename($record);
        }

        return $record;
    }

    private function remove(Model $record): int
    {
        $id = $record->getKey();
        $record->delete();

        return $id;
    }

    /** The values of an operation with the ids of records made by earlier ones. */
    private function linked(array $op, array $made): array
    {
        $values = $op['after'] ?? [];
        foreach ($op['links'] ?? [] as $field => $index) {
            $values[$field] = $made[$index] ?? null;
        }

        return $values;
    }

    private function find(array $op, bool $fail = true): ?Model
    {
        $class = self::MODELS[$op['model']][0];
        $record = $class::query()->find($op['id']);
        if (! $record && $fail) {
            throw new HrRefused('Գրառումներից մեկն արդեն ջնջված է։');
        }

        return $record;
    }

    private function locked(HrChange $change): HrChange
    {
        Tenant::query()->whereKey($this->tenants->id())->lockForUpdate()->first();

        return HrChange::query()->lockForUpdate()->findOrFail($change->id);
    }
}
