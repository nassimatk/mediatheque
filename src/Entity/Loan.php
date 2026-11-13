<?php

namespace App\Entity;

use App\Repository\LoanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un prêt : un exemplaire prêté à un adhérent.
 * Il n'y a pas de champ « statut » : l'état se déduit des dates (voir isOpen / isOverdue).
 */
#[ORM\Entity(repositoryClass: LoanRepository::class)]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Copy $copy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $member = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $borrowedAt;

    /** Date limite de retour (repoussée à chaque prolongation). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    /** Vide tant que le prêt est en cours ; renseignée au retour (ou si l'exemplaire est déclaré perdu). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $renewalCount = 0;

    public function __construct()
    {
        $this->borrowedAt = new \DateTimeImmutable();
        $this->dueAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCopy(): ?Copy
    {
        return $this->copy;
    }

    public function setCopy(?Copy $copy): static
    {
        $this->copy = $copy;

        return $this;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getBorrowedAt(): \DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function setBorrowedAt(\DateTimeImmutable $borrowedAt): static
    {
        $this->borrowedAt = $borrowedAt;

        return $this;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getRenewalCount(): int
    {
        return $this->renewalCount;
    }

    public function setRenewalCount(int $renewalCount): static
    {
        $this->renewalCount = $renewalCount;

        return $this;
    }

    /** Un prêt est en cours tant qu'il n'est pas clos. */
    public function isOpen(): bool
    {
        return null === $this->closedAt;
    }

    /** Un prêt en cours est en retard si sa date limite est dépassée. */
    public function isOverdue(\DateTimeImmutable $now): bool
    {
        return $this->isOpen() && $this->dueAt < $now;
    }

    public function __toString(): string
    {
        return sprintf('Prêt n°%s', $this->id ?? 'nouveau');
    }
}
