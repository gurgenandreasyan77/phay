<?php

namespace App\Http\Requests\Admin;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCalculatorSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'bathroom_rate' => ['required', 'integer', 'min:0'],
            'vitrage_rate' => ['required', 'integer', 'min:0'],
            'extra_floor_rate' => ['required', 'integer', 'min:0'],
            'heavy_soiling_multiplier' => ['required', 'numeric', 'min:0', 'max:1'],
            'cleaning_minimum' => ['required', 'integer', 'min:0'],
            'cleaning_rates' => ['required', 'array'],
            'room_rates' => ['required', 'array'],
            'cleaning_discounts' => ['required', 'array'],
            'dry_cleaning_rates' => ['required', 'array'],
            'dry_cleaning_rates.sofa2' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.sofa3' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.sofaCorner' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.armchair' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.chair' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.mattressSingle' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.mattressDouble' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.carpet' => ['required', 'integer', 'min:0'],
            'dry_cleaning_rates.steam' => ['required', 'integer', 'min:0'],
        ];

        foreach (PropertyType::cases() as $propertyType) {
            if ($propertyType === PropertyType::Other) {
                continue;
            }

            foreach (['regular', 'general', 'postConstruction'] as $cleaningType) {
                $rules["cleaning_rates.{$propertyType->value}.{$cleaningType}"] = ['required', 'integer', 'min:0'];
            }
        }

        foreach (['studio', 'oneRoom', 'twoRoom', 'threeRoom', 'fourPlusRoom'] as $roomType) {
            foreach (['regular', 'general', 'postConstruction'] as $cleaningType) {
                $rules["room_rates.{$roomType}.{$cleaningType}"] = ['required', 'integer', 'min:0'];
            }
        }

        foreach (['regular', 'general', 'postConstruction'] as $cleaningType) {
            $rules["cleaning_discounts.{$cleaningType}"] = ['required', 'integer', 'min:0', 'max:100'];
        }

        return $rules;
    }
}
