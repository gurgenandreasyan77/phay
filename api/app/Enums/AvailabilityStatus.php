<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case PreOrder = 'pre_order';
}
