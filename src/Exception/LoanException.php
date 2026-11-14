<?php

namespace App\Exception;

/**
 * Exception levée lorsqu'une règle métier de prêt est violée.
 */
class LoanException extends \DomainException
{
    public const COPY_NOT_AVAILABLE               = 'copy_not_available';
    public const COPY_RESERVED_FOR_ANOTHER_MEMBER = 'copy_reserved_for_another_member';
    public const COPY_IN_TRANSIT                  = 'copy_in_transit';
    public const QUOTA_REACHED                    = 'quota_reached';
    public const UNPAID_PENALTY                   = 'unpaid_penalty';
    public const MAX_RENEWALS_REACHED             = 'max_renewals_reached';
    public const RESERVATION_EXISTS               = 'reservation_exists';
    public const LOAN_ALREADY_CLOSED              = 'loan_already_closed';

    public static function copyNotAvailable(): self
    {
        return new self('Cet exemplaire n\'est pas disponible à l\'emprunt.');
    }

    public static function copyReservedForAnotherMember(): self
    {
        return new self('Cet exemplaire est mis de côté pour un autre adhérent.');
    }

    public static function copyInTransit(): self
    {
        return new self('Cet exemplaire est actuellement en transit entre deux médiathèques.');
    }

    public static function quotaReached(int $max): self
    {
        return new self(
            sprintf('Quota atteint : vous ne pouvez pas avoir plus de %d prêt(s) simultané(s).', $max)
        );
    }

    public static function unpaidPenalty(): self
    {
        return new self('Prêt impossible : l\'adhérent a une pénalité impayée.');
    }

    public static function maxRenewalsReached(int $max): self
    {
        return new self(
            sprintf('Nombre maximal de prolongations atteint (%d).', $max)
        );
    }

    public static function reservationExists(): self
    {
        return new self('Prolongation impossible : une réservation est en attente sur cette édition.');
    }

    public static function loanAlreadyClosed(): self
    {
        return new self('Ce prêt est déjà clôturé.');
    }
}
