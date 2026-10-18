<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Groups of employees and their managers. Everyone who keeps orders reads
 * them (the performer field groups names by them); the owner and the admin
 * keep them.
 */
class EmployeeGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = EmployeeGroup::query()
            ->with('manager:id,name')
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups->map(fn (EmployeeGroup $g) => self::toArray($g))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = EmployeeGroup::create($this->validated($request));

        return response()->json(['data' => self::toArray($group->load('manager:id,name')->loadCount('employees'))], 201);
    }

    public function update(Request $request, EmployeeGroup $employeeGroup): JsonResponse
    {
        $employeeGroup->update($this->validated($request, $employeeGroup));

        return response()->json(['data' => self::toArray($employeeGroup->load('manager:id,name')->loadCount('employees'))]);
    }

    /** Its employees stay, in no group. */
    public function destroy(EmployeeGroup $employeeGroup): Response
    {
        Employee::query()->where('group_id', $employeeGroup->id)->update(['group_id' => null]);
        $employeeGroup->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?EmployeeGroup $group = null): array
    {
        $validated = $request->validate([
            'name' => [$group ? 'sometimes' : 'required', 'string', 'max:100'],
            'managerId' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = preg_replace('/\s+/u', ' ', trim($validated['name']));
            $taken = EmployeeGroup::query()
                ->when($group, fn ($q) => $q->whereKeyNot($group->id))
                ->get(['id', 'name'])
                ->contains(fn (EmployeeGroup $g) => Employee::normalize($g->name) === Employee::normalize($validated['name']));

            if ($validated['name'] === '' || $taken) {
                throw ValidationException::withMessages(['name' => [$taken ? 'Այս անունով խումբ արդեն կա։' : 'Պարտադիր դաշտ է։']]);
            }
        }

        if (array_key_exists('managerId', $validated)) {
            // Only an employee of this business can manage it.
            if ($validated['managerId'] !== null && ! Employee::query()->whereKey($validated['managerId'])->exists()) {
                throw ValidationException::withMessages(['managerId' => ['Ընտրեք աշխատակից ցանկից։']]);
            }
            $validated['manager_id'] = $validated['managerId'];
            unset($validated['managerId']);
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    public static function toArray(EmployeeGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'manager' => $group->manager ? ['id' => $group->manager->id, 'name' => $group->manager->name] : null,
            'employeesCount' => $group->employees_count ?? null,
        ];
    }
}
