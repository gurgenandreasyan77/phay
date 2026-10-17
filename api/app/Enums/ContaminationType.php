<?php

namespace App\Enums;

enum ContaminationType: string
{
    case Dust = 'dust';
    case FoodDrinks = 'food_drinks';
    case Coffee = 'coffee';
    case GreaseOil = 'grease_oil';
    case Blood = 'blood';
    case Urine = 'urine';
    case Vomit = 'vomit';
    case Ink = 'ink';
    case PetHair = 'pet_hair';
    case TobaccoOdor = 'tobacco_odor';
    case Dampness = 'dampness';
    case Unknown = 'unknown';
}
