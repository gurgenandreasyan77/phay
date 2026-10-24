<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\FeatureFlagLog;
use App\Sites\SiteCacheTag;
use App\Sites\SiteRevalidator;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map($this->present(...), FeatureFlag::cases()),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        Gate::authorize('manage-feature-flags');

        $flag = FeatureFlag::tryFrom($key);

        if (! $flag) {
            throw ValidationException::withMessages(['key' => ['Անհայտ feature flag։']]);
        }

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $oldValue = Feature::active($flag->value);
        $newValue = $validated['active'];

        if ($newValue) {
            Feature::activate($flag->value);
        } else {
            Feature::deactivate($flag->value);
        }

        if ($oldValue !== $newValue) {
            FeatureFlagLog::create([
                'feature' => $flag->value,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed_by_id' => $request->user()->id,
            ]);

            // A module turned on or off changes what every public endpoint answers.
            app(SiteRevalidator::class)->touch(app(TenantContext::class)->id(), ...SiteCacheTag::cases());
        }

        return response()->json(['data' => $this->present($flag)]);
    }

    private function present(FeatureFlag $flag): array
    {
        $lastLog = FeatureFlagLog::query()
            ->where('feature', $flag->value)
            ->latest('id')
            ->with('changedBy')
            ->first();

        return [
            'key' => $flag->value,
            'label' => $flag->label(),
            'description' => $flag->description(),
            'category' => $flag->category(),
            'active' => Feature::active($flag->value),
            'lastChangedAt' => $lastLog?->created_at?->toIso8601String(),
            'lastChangedBy' => $lastLog?->changedBy?->name,
        ];
    }
}
