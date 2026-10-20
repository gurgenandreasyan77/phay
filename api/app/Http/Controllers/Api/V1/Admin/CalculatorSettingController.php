<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCalculatorSettingRequest;
use App\Http\Resources\CalculatorSettingResource;
use App\Models\CalculatorSetting;
use Illuminate\Http\JsonResponse;

/**
 * The price calculator of the services module. A business has none until
 * someone adds it (it starts with the cleaning rates Servio began with);
 * reading never makes one.
 */
class CalculatorSettingController extends Controller
{
    public function show(): CalculatorSettingResource
    {
        return new CalculatorSettingResource(CalculatorSetting::existing() ?? abort(404));
    }

    public function store(): JsonResponse
    {
        $setting = CalculatorSetting::current();

        return (new CalculatorSettingResource($setting))->response()
            ->setStatusCode($setting->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateCalculatorSettingRequest $request): JsonResponse
    {
        $setting = CalculatorSetting::existing() ?? abort(404);
        $setting->update($request->validated());

        return (new CalculatorSettingResource($setting->fresh()))->response();
    }
}
