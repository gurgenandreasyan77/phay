<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Subscription plans of the platform (super admin only): their names,
 * monthly prices, trial days, the modules they include and the AI
 * assistant requests a month.
 */
class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::query()->withCount('subscriptions')->ordered()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $plan = DB::transaction(fn () => $this->save(new Plan, $this->validated($request)));

        return (new PlanResource($plan->loadCount('subscriptions')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan): PlanResource
    {
        $plan = DB::transaction(fn () => $this->save($plan, $this->validated($request, partial: true)));

        return new PlanResource($plan->loadCount('subscriptions'));
    }

    /** Only a plan no business is on; otherwise it can be turned off (is_active). */
    public function destroy(Plan $plan): JsonResponse
    {
        abort_if(
            $plan->subscriptions()->exists(),
            422,
            'Այս փաթեթով բիզնեսներ կան։ Փոխեք նրանց փաթեթը կամ անջատեք այն։',
        );

        $plan->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'price' => [$required, 'integer', 'min:0', 'max:100000000'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            // AI assistant requests a subscription month; null: no limit, 0: none.
            'assistant_requests' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'included_modules' => ['sometimes', 'array'],
            // Modules and AI agents ("agent:analyst") whose price the plan includes.
            'included_modules.*' => ['string', Rule::in([...array_column(FeatureFlag::cases(), 'value'), ...AgentCatalog::priceKeys()])],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function save(Plan $plan, array $data): Plan
    {
        if (array_key_exists('included_modules', $data)) {
            $data['included_modules'] = array_values(array_unique($data['included_modules']));
        }

        $plan->fill($data);
        $plan->included_modules ??= [];
        $plan->save();

        // One default plan: the one new businesses start on.
        if ($plan->is_default) {
            Plan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
        }

        return $plan;
    }
}
