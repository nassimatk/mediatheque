<?php

namespace App\Service\Integration\OpenLibrary;

/**
 * Objet de transfert contenant les données brutes extraites d'une réponse Open Library.
 * Tous les champs sont nullable : Open Library peut ne pas les fournir.
 */
final class BookDto
{
    /**
     * @param string[] $authors Noms des auteurs (peut être vide si Open Library ne les fournit pas)
     */
    public function __construct(
        public readonly string  $isbn13,
        public readonly ?string $title,
        public readonly array   $authors,
        public readonly ?string $publisher,
        public readonly ?int    $publicationYear,
        public readonly ?string $coverUrl,
    ) {}
}
