<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CalculatorSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'cleaning_rates',
        'room_rates',
        'cleaning_discounts',
        'bathroom_rate',
        'vitrage_rate',
        'extra_floor_rate',
        'heavy_soiling_multiplier',
        'cleaning_minimum',
        'dry_cleaning_rates',
    ];

    protected $casts = [
        'cleaning_rates' => 'array',
        'room_rates' => 'array',
        'cleaning_discounts' => 'array',
        'bathroom_rate' => 'integer',
        'vitrage_rate' => 'integer',
        'extra_floor_rate' => 'integer',
        'heavy_soiling_multiplier' => 'float',
        'cleaning_minimum' => 'integer',
        'dry_cleaning_rates' => 'array',
    ];

    // Calculator rates are per business, not per record — callers always want
    // the current business's one row. Defaults here mirror the original
    // hardcoded placeholder rates from src/config/pricing.ts, so the very
    // first read (before the seeder runs) doesn't produce a broken calculator.
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'cleaning_rates' => [
                'apartment' => ['regular' => 350, 'general' => 600, 'postConstruction' => 950],
                'house' => ['regular' => 400, 'general' => 700, 'postConstruction' => 1050],
                'office' => ['regular' => 300, 'general' => 500, 'postConstruction' => 850],
            ],
            // Flat "from" prices shown per apartment room count, independent of
            // the m²-based cleaning_rates above — a quick-glance estimate.
            'room_rates' => [
                'studio' => ['regular' => 9000, 'general' => 14000, 'postConstruction' => 22000],
                'oneRoom' => ['regular' => 14000, 'general' => 20000, 'postConstruction' => 32000],
                'twoRoom' => ['regular' => 25000, 'general' => 34000, 'postConstruction' => 52000],
                'threeRoom' => ['regular' => 34000, 'general' => 46000, 'postConstruction' => 70000],
                'fourPlusRoom' => ['regular' => 45000, 'general' => 60000, 'postConstruction' => 90000],
            ],
            'cleaning_discounts' => [
                'regular' => 0,
                'general' => 0,
                'postConstruction' => 0,
            ],
            'bathroom_rate' => 2500,
            'vitrage_rate' => 500,
            'extra_floor_rate' => 5000,
            'heavy_soiling_multiplier' => 0.15,
            'cleaning_minimum' => 25000,
            'dry_cleaning_rates' => [
                'sofa2' => 15000,
                'sofa3' => 20000,
                'sofaCorner' => 28000,
                'armchair' => 8000,
                'chair' => 3000,
                'mattressSingle' => 12000,
                'mattressDouble' => 18000,
                'carpet' => 1200,
                'steam' => 5000,
            ],
        ]);
    }
}
