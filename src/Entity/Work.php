<?php

namespace App\Entity;

use App\Repository\WorkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Une œuvre : la création en elle-même (ex. « Les Misérables »), quelle que soit l'édition. */
#[ORM\Entity(repositoryClass: WorkRepository::class)]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    /** @var Collection<int, Edition> */
    #[ORM\OneToMany(targetEntity: Edition::class, mappedBy: 'work')]
    private Collection $editions;

    public function __construct()
    {
        $this->editions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** @return Collection<int, Edition> */
    public function getEditions(): Collection
    {
        return $this->editions;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
