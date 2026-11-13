<?php

namespace App\Entity;

use App\Enum\PenaltyType;
use App\Repository\PenaltyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une pénalité liée à un prêt (retard, perte, dégât). L'adhérent concerné est celui du prêt. */
#[ORM\Entity(repositoryClass: PenaltyRepository::class)]
class Penalty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Loan $loan = null;

    #[ORM\Column(length: 20, enumType: PenaltyType::class)]
    private PenaltyType $type = PenaltyType::Late;

    /** Montant en centimes de dirham. */
    #[ORM\Column]
    private int $amountCents = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Vide tant que la pénalité n'est pas payée. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoan(): ?Loan
    {
        return $this->loan;
    }

    public function setLoan(?Loan $loan): static
    {
        $this->loan = $loan;

        return $this;
    }

    public function getType(): PenaltyType
    {
        return $this->type;
    }

    public function setType(PenaltyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function setAmountCents(int $amountCents): static
    {
        $this->amountCents = $amountCents;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function isPaid(): bool
    {
        return null !== $this->paidAt;
    }

    public function __toString(): string
    {
        return sprintf('%s : %.2f MAD', $this->type->label(), $this->amountCents / 100);
    }
}
