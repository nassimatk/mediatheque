<?php

namespace App\Entity;

use App\Repository\SubscriptionPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une formule d'abonnement : elle fixe les règles de prêt de ses adhérents. */
#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    /** Nombre maximal de prêts en cours en même temps. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxLoans = 3;

    /** Durée d'un prêt, en jours. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $loanDays = 21;

    /** Nombre maximal de prolongations par prêt. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxRenewals = 1;

    /** Pénalité par jour de retard, en centimes de dirham. */
    #[ORM\Column]
    private int $penaltyCentsPerDay = 100;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMaxLoans(): int
    {
        return $this->maxLoans;
    }

    public function setMaxLoans(int $maxLoans): static
    {
        $this->maxLoans = $maxLoans;

        return $this;
    }

    public function getLoanDays(): int
    {
        return $this->loanDays;
    }

    public function setLoanDays(int $loanDays): static
    {
        $this->loanDays = $loanDays;

        return $this;
    }

    public function getMaxRenewals(): int
    {
        return $this->maxRenewals;
    }

    public function setMaxRenewals(int $maxRenewals): static
    {
        $this->maxRenewals = $maxRenewals;

        return $this;
    }

    public function getPenaltyCentsPerDay(): int
    {
        return $this->penaltyCentsPerDay;
    }

    public function setPenaltyCentsPerDay(int $penaltyCentsPerDay): static
    {
        $this->penaltyCentsPerDay = $penaltyCentsPerDay;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
