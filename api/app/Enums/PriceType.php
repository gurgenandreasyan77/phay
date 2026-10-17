<?php

namespace App\Enums;

enum PriceType: string
{
    case Fixed = 'fixed';
    case From = 'from';
    case OnInspection = 'on_inspection';
    case Range = 'range';
    case ByCarType = 'by_car_type';
}
