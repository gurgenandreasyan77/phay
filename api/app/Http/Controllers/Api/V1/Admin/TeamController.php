<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\JobOpening;
use App\Team\AbsenceKind;
use App\Team\CandidateStage;
use App\Team\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The team's time off and hiring, kept by hand in the admin (the HR agent
 * prepares the same as cards): absences of a range of days, job openings
 * and their candidates. Owner and admin.
 */
class TeamController extends Controller
{
    public function absences(Request $request, Team $team): JsonResponse
    {
        $today = $team->today();
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        // By default: the last month and everything planned ahead.
        $from = $validated['from'] ?? $today->subMonth()->toDateString();
        $to = $validated['to'] ?? $today->addYear()->toDateString();

        return response()->json(['data' => [
            'today' => $today->toDateString(),
            'absences' => $team->absences($from, $to)->map(fn (EmployeeAbsence $a) => Team::absence($a))->values(),
            'vacationDays' => (object) $team->vacationDays($today->year),
        ]]);
    }

    public function storeAbsence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employeeId' => ['required', 'integer'],
            'kind' => ['required', Rule::enum(AbsenceKind::class)],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = Employee::query()->find($validated['employeeId'])
            ?? throw ValidationException::withMessages(['employeeId' => ['Ընտրեք աշխատակցին։']]);
        if (EmployeeAbsence::query()->where('employee_id', $employee->id)->overlapping($validated['from'], $validated['to'])->exists()) {
            throw ValidationException::withMessages(['from' => ["{$employee->name}-ն այդ օրերին արդեն բացակա է։"]]);
        }

        $absence = EmployeeAbsence::create([
            'employee_id' => $employee->id,
            'kind' => $validated['kind'],
            'starts_on' => $validated['from'],
            'ends_on' => $validated['to'],
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => Team::absence($absence->load('employee'))], 201);
    }

    public function destroyAbsence(EmployeeAbsence $absence): Response
    {
        $absence->delete();

        return response()->noContent();
    }

    public function hiring(Team $team): JsonResponse
    {
        return response()->json(['data' => [
            'openings' => $team->openings()->map(fn (JobOpening $o) => Team::opening($o))->values(),
            'candidates' => $team->candidates()->map(fn (Candidate $c) => Team::candidate($c))->values(),
            'stages' => collect(CandidateStage::cases())->map(fn (CandidateStage $s) => ['value' => $s->value, 'label' => $s->label()]),
        ]]);
    }

    public function storeOpening(Request $request): JsonResponse
    {
        $opening = JobOpening::create($this->opening($request));

        return response()->json(['data' => Team::opening($opening)], 201);
    }

    public function updateOpening(Request $request, JobOpening $opening): JsonResponse
    {
        $opening->update($this->opening($request, $opening));

        return response()->json(['data' => Team::opening($opening)]);
    }

    public function destroyOpening(JobOpening $opening): Response
    {
        $opening->delete();

        return response()->noContent();
    }

    public function storeCandidate(Request $request): JsonResponse
    {
        $candidate = Candidate::create(['stage' => CandidateStage::New->value, ...$this->candidate($request)]);

        return response()->json(['data' => Team::candidate($candidate->fresh())], 201);
    }

    public function updateCandidate(Request $request, Candidate $candidate): JsonResponse
    {
        $candidate->update($this->candidate($request, $candidate));

        return response()->json(['data' => Team::candidate($candidate->fresh())]);
    }

    public function destroyCandidate(Candidate $candidate): Response
    {
        $candidate->delete();

        return response()->noContent();
    }

    private function opening(Request $request, ?JobOpening $opening = null): array
    {
        return $request->validate([
            'title' => [$opening ? 'sometimes' : 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'salary' => ['sometimes', 'nullable', 'string', 'max:100'],
            'employment' => ['sometimes', 'nullable', Rule::in(JobOpening::EMPLOYMENT)],
            'status' => ['sometimes', Rule::in(JobOpening::STATUSES)],
        ]);
    }

    private function candidate(Request $request, ?Candidate $candidate = null): array
    {
        $validated = $request->validate([
            'name' => [$candidate ? 'sometimes' : 'required', 'string', 'max:100'],
            'openingId' => ['sometimes', 'nullable', 'integer'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'string', 'max:150'],
            'source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'stage' => ['sometimes', Rule::enum(CandidateStage::class)],
            'interviewAt' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i'],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('openingId', $validated)) {
            if ($validated['openingId'] !== null && ! JobOpening::query()->whereKey($validated['openingId'])->exists()) {
                throw ValidationException::withMessages(['openingId' => ['Ընտրեք հաստիքը ցանկից։']]);
            }
            $validated['job_opening_id'] = $validated['openingId'];
            unset($validated['openingId']);
        }
        if (array_key_exists('interviewAt', $validated)) {
            $validated['interview_at'] = $validated['interviewAt'] ? $validated['interviewAt'].':00' : null;
            unset($validated['interviewAt']);
        }

        return $validated;
    }
}
