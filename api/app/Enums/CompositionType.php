<?php

namespace App\Enums;

enum CompositionType: string
{
    case Acidic = 'acidic';
    case Neutral = 'neutral';
    case Alkaline = 'alkaline';
    case Enzymatic = 'enzymatic';
    case Oxidizing = 'oxidizing';
    case Solvent = 'solvent';
}
