<?php

namespace App\Entity;

use App\Enum\CopyStatus;
use App\Repository\CopyRepository;
use Doctrine\ORM\Mapping as ORM;

/** Un exemplaire : l'objet physique (code-barres) qui se trouve sur un site et qui est prêté. */
#[ORM\Entity(repositoryClass: CopyRepository::class)]
class Copy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'copies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Edition $edition = null;

    /** Site où l'exemplaire se trouve actuellement. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $site = null;

    /** Site vers lequel l'exemplaire voyage (renseigné seulement quand il est « en transit »). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Site $destinationSite = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $barcode = '';

    #[ORM\Column(length: 20, enumType: CopyStatus::class)]
    private CopyStatus $status = CopyStatus::Available;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getDestinationSite(): ?Site
    {
        return $this->destinationSite;
    }

    public function setDestinationSite(?Site $destinationSite): static
    {
        $this->destinationSite = $destinationSite;

        return $this;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getStatus(): CopyStatus
    {
        return $this->status;
    }

    public function setStatus(CopyStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function __toString(): string
    {
        return $this->barcode;
    }
}
