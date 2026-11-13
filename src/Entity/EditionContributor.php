<?php

namespace App\Entity;

use App\Enum\ContributorRole;
use App\Repository\EditionContributorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien « une personne a joué un rôle sur une édition ».
 * Une même personne peut avoir plusieurs rôles sur une édition (auteur et illustrateur),
 * mais pas deux fois le même : d'où la contrainte d'unicité.
 */
#[ORM\Entity(repositoryClass: EditionContributorRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_edition_contributor_role', columns: ['edition_id', 'contributor_id', 'role'])]
class EditionContributor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Edition $edition = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contributor $contributor = null;

    #[ORM\Column(length: 20, enumType: ContributorRole::class)]
    private ContributorRole $role = ContributorRole::Author;

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

    public function getContributor(): ?Contributor
    {
        return $this->contributor;
    }

    public function setContributor(?Contributor $contributor): static
    {
        $this->contributor = $contributor;

        return $this;
    }

    public function getRole(): ContributorRole
    {
        return $this->role;
    }

    public function setRole(ContributorRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->contributor, $this->role->label());
    }
}
