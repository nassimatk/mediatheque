<?php

namespace App\Enum;

/** État d'un exemplaire physique. */
enum CopyStatus: string
{
    case Available = 'available';
    case Borrowed = 'borrowed';
    case Reserved = 'reserved';
    case InTransit = 'in_transit';
    case Lost = 'lost';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Borrowed => 'En prêt',
            self::Reserved => 'Mis de côté',
            self::InTransit => 'En transit',
            self::Lost => 'Perdu',
            self::Damaged => 'Abîmé',
        };
    }
}
