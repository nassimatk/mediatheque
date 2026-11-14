<?php

namespace App\Exception;

/**
 * Exception levée lorsqu'une règle métier de réservation ou de circulation est violée.
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

    public static function copyAvailableOnSite(): self
    {
        return new self('Un exemplaire est directement disponible dans votre médiathèque de retrait. Vous pouvez l\'emprunter en rayon.');
    }

    public static function copyNotUnderReservation(): self
    {
        return new self('Cet exemplaire n\'est pas attribué à cette réservation.');
    }

    public static function copyNotInTransit(): self
    {
        return new self('Cet exemplaire n\'est pas actuellement en transit.');
    }

    public static function invalidTransitDestination(): self
    {
        return new self('Le site de réception ne correspond pas à la destination de transit prévue.');
    }
}
