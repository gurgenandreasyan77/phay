<?php

namespace App\Team;

/** Where a candidate is on the way to a job. */
enum CandidateStage: string
{
    case New = 'new';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Նոր',
            self::Screening => 'Զանգ / ստուգում',
            self::Interview => 'Հարցազրույց',
            self::Offer => 'Առաջարկ',
            self::Hired => 'Ընդունված',
            self::Rejected => 'Մերժված',
        };
    }

    /** Still on the way (not hired, not rejected). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Hired, self::Rejected], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
