<?php

namespace App\Enums;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Office = 'office';
    case Other = 'other';
}
