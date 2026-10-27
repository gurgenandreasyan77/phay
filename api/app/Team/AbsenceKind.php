<?php

namespace App\Team;

/** Why an employee is away. */
enum AbsenceKind: string
{
    case Vacation = 'vacation';
    case DayOff = 'day_off';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Արձակուրդ',
            self::DayOff => 'Հանգստյան օր',
            self::Sick => 'Հիվանդություն',
            self::Unpaid => 'Չվճարվող արձակուրդ',
            self::Other => 'Բացակայություն',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
