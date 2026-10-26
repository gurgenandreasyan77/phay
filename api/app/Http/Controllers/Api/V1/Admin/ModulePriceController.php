<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\Agent;
use App\Agents\AgentCatalog;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\ModulePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The monthly price of each module and AI agent (super admin only): what a
 * business adds to its plan's price for a module or agent it has on. An
 * agent's key is "agent:<key>".
 */
class ModulePriceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->present()]);
    }

    public function update(Request $request): JsonResponse
    {
        $keys = [...array_column(FeatureFlag::cases(), 'value'), ...AgentCatalog::priceKeys()];

        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*' => ['integer', 'min:0', 'max:100000000'],
        ]);

        foreach ($validated['prices'] as $key => $price) {
            abort_unless(in_array($key, $keys, true), 422, "Unknown module [{$key}].");

            ModulePrice::query()->updateOrCreate(['feature_key' => $key], ['price' => $price]);
        }

        return response()->json(['data' => $this->present()]);
    }

    /** @return list<array{key: string, kind: string, label: string, description: string, price: int}> */
    private function present(): array
    {
        $prices = ModulePrice::map();

        return [
            ...array_map(fn (FeatureFlag $flag) => [
                'key' => $flag->value,
                'kind' => 'module',
                'label' => $flag->label(),
                'description' => $flag->description(),
                'price' => $prices[$flag->value] ?? 0,
            ], FeatureFlag::cases()),
            ...array_map(fn (Agent $agent) => [
                'key' => $agent->priceKey(),
                'kind' => 'agent',
                'label' => 'AI · '.$agent->label(),
                'description' => $agent->description(),
                'price' => $prices[$agent->priceKey()] ?? $agent->defaultPrice(),
            ], array_values(AgentCatalog::all())),
        ];
    }
}
