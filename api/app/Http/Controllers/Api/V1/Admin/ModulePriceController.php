<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\ModulePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The monthly price of each module (super admin only): what a business adds
 * to its plan's price for a module it has on.
 */
class ModulePriceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->present()]);
    }

    public function update(Request $request): JsonResponse
    {
        $keys = array_column(FeatureFlag::cases(), 'value');

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

    /** @return list<array{key: string, label: string, description: string, price: int}> */
    private function present(): array
    {
        $prices = ModulePrice::map();

        return array_map(fn (FeatureFlag $flag) => [
            'key' => $flag->value,
            'label' => $flag->label(),
            'description' => $flag->description(),
            'price' => $prices[$flag->value] ?? 0,
        ], FeatureFlag::cases());
    }
}
