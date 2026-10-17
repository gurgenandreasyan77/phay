<?php

namespace App\Enums;

enum CarBodyType: string
{
    case Compact = 'compact';
    case Sedan = 'sedan';
    case Wagon = 'wagon';
    case Crossover = 'crossover';
    case Suv = 'suv';
    case Minivan = 'minivan';
    case Minibus = 'minibus';
    case Commercial = 'commercial';
}
