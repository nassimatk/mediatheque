<?php

namespace App\Entity;

use App\Repository\ContributorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Une personne créditée sur des documents : auteur, traducteur, illustrateur, réalisateur. */
#[ORM\Entity(repositoryClass: ContributorRepository::class)]
class Contributor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    /** @var Collection<int, EditionContributor> */
    #[ORM\OneToMany(targetEntity: EditionContributor::class, mappedBy: 'contributor')]
    private Collection $contributions;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
    }

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

    /** @return Collection<int, EditionContributor> */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
