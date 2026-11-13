<?php

namespace App\Entity;

use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une réservation : un adhérent attend une édition. Les réservations d'une même édition
 * forment une file d'attente, ordonnée par date de création (premier arrivé, premier servi).
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $member = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Edition $edition = null;

    /** Site où l'adhérent viendra retirer le document. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $pickupSite = null;

    /** Exemplaire mis de côté pour cette réservation (vide tant qu'aucun n'est attribué). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Copy $copy = null;

    #[ORM\Column(length: 20, enumType: ReservationStatus::class)]
    private ReservationStatus $status = ReservationStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Date limite de retrait une fois l'exemplaire mis de côté. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $holdUntil = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEdition(): ?Edition
    {
        return $this->edition;
    }

    public function setEdition(?Edition $edition): static
    {
        $this->edition = $edition;

        return $this;
    }

    public function getPickupSite(): ?Site
    {
        return $this->pickupSite;
    }

    public function setPickupSite(?Site $pickupSite): static
    {
        $this->pickupSite = $pickupSite;

        return $this;
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

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function setStatus(ReservationStatus $status): static
    {
        $this->status = $status;

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

    public function getHoldUntil(): ?\DateTimeImmutable
    {
        return $this->holdUntil;
    }

    public function setHoldUntil(?\DateTimeImmutable $holdUntil): static
    {
        $this->holdUntil = $holdUntil;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Réservation n°%s', $this->id ?? 'nouvelle');
    }
}
