<?php

namespace App\Exception;

/**
 * Exception levée lorsqu'une règle métier de réservation est violée.
 */
class ReservationException extends \DomainException
{
    public static function alreadyReserved(): self
    {
        return new self('Vous avez déjà une réservation active pour cette édition.');
    }

    public static function reservationNotActive(): self
    {
        return new self('Cette réservation ne peut pas être annulée (statut incompatible).');
    }
}
