<?php

namespace App\Enum;

/** Motif d'une pénalité. */
enum PenaltyType: string
{
    case Late = 'late';
    case Lost = 'lost';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Late => 'Retard',
            self::Lost => 'Perdu',
            self::Damaged => 'Abîmé',
        };
    }
}
