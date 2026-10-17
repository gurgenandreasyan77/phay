<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalculatorSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cleaningRates' => $this->cleaning_rates,
            'roomRates' => $this->room_rates,
            'cleaningDiscounts' => $this->cleaning_discounts,
            'bathroomRate' => $this->bathroom_rate,
            'vitrageRate' => $this->vitrage_rate,
            'extraFloorRate' => $this->extra_floor_rate,
            'heavySoilingMultiplier' => $this->heavy_soiling_multiplier,
            'cleaningMinimum' => $this->cleaning_minimum,
            'dryCleaningRates' => $this->dry_cleaning_rates,
        ];
    }
}
