<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalculatorSettingResource;
use App\Models\CalculatorSetting;
use Illuminate\Http\JsonResponse;

class CalculatorSettingController extends Controller
{
    public function show(): JsonResponse
    {
        // ::current() may transparently create the singleton row on first
        // read (firstOrCreate) — Laravel's JsonResource response would then
        // default to 201 because the wrapped model's wasRecentlyCreated is
        // true, which is wrong for what is otherwise a plain GET.
        return (new CalculatorSettingResource(CalculatorSetting::current()))->response()->setStatusCode(200);
    }
}
