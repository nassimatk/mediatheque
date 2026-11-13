<?php

namespace App\Enum;

/** Cycle de vie d'une réservation : En attente -> Prête -> Honorée (ou Expirée / Annulée). */
enum ReservationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Fulfilled = 'fulfilled';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Ready => 'Prête',
            self::Fulfilled => 'Honorée',
            self::Expired => 'Expirée',
            self::Cancelled => 'Annulée',
        };
    }
}
