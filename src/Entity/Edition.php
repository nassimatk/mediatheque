<?php

namespace App\Entity;

use App\Enum\EditionType;
use App\Repository\EditionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une édition : une publication précise d'une œuvre (ISBN, éditeur, année, support). */
#[ORM\Entity(repositoryClass: EditionRepository::class)]
class Edition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'editions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Work $work = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    /** Unique, mais facultatif (certains documents n'ont pas d'ISBN). */
    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 20, enumType: EditionType::class)]
    private EditionType $type = EditionType::Book;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $publicationYear = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverUrl = null;

    /** @var Collection<int, Copy> */
    #[ORM\OneToMany(targetEntity: Copy::class, mappedBy: 'edition')]
    private Collection $copies;

    /** @var Collection<int, EditionContributor> */
    #[ORM\OneToMany(targetEntity: EditionContributor::class, mappedBy: 'edition')]
    private Collection $contributions;

    public function __construct()
    {
        $this->copies = new ArrayCollection();
        $this->contributions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWork(): ?Work
    {
        return $this->work;
    }

    public function setWork(?Work $work): static
    {
        $this->work = $work;

        return $this;
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

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getType(): EditionType
    {
        return $this->type;
    }

    public function setType(EditionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublicationYear(): ?int
    {
        return $this->publicationYear;
    }

    public function setPublicationYear(?int $publicationYear): static
    {
        $this->publicationYear = $publicationYear;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

        return $this;
    }

    /** @return Collection<int, Copy> */
    public function getCopies(): Collection
    {
        return $this->copies;
    }

    /** @return Collection<int, EditionContributor> */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
