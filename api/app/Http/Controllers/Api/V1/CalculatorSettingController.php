<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalculatorSettingResource;
use App\Models\CalculatorSetting;

class CalculatorSettingController extends Controller
{
    /** Only a business whose services have a calculator (added in its admin) shows one. */
    public function show(): CalculatorSettingResource
    {
        return new CalculatorSettingResource(CalculatorSetting::existing() ?? abort(404));
    }
}
