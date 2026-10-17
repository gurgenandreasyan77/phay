<?php

namespace Database\Seeders;

use App\Models\CalculatorSetting;
use Illuminate\Database\Seeder;

class CalculatorSettingSeeder extends Seeder
{
    // CalculatorSetting::current() lazily creates the singleton row with
    // sane defaults on first read, but seeding it explicitly means the row
    // exists deterministically right after `db:seed`, not on the first
    // request to hit the calculator.
    public function run(): void
    {
        CalculatorSetting::current();
    }
}
