<?php

namespace App\Enum;

/** Rôle d'une personne sur une édition. */
enum ContributorRole: string
{
    case Author = 'author';
    case Translator = 'translator';
    case Illustrator = 'illustrator';
    case Director = 'director';

    public function label(): string
    {
        return match ($this) {
            self::Author => 'Auteur',
            self::Translator => 'Traducteur',
            self::Illustrator => 'Illustrateur',
            self::Director => 'Réalisateur',
        };
    }
}
