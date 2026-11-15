<?php

namespace App\Service\Integration\OpenLibrary;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Client HTTP vers l'API Open Library Books.
 *
 * Documentation : https://openlibrary.org/dev/docs/api/books
 * Endpoint utilisé : GET /api/books?bibkeys=ISBN:xxx&format=json&jscmd=data
 *
 * Règles d'usage respectées :
 * - User-Agent avec nom de l'application et email de contact (3 req/s autorisées).
 * - Requête groupée : plusieurs ISBN en un seul appel HTTP (séparés par virgule).
 * - Pas d'appel si l'ISBN est déjà connu en base (géré par IsbnImporter).
 * - On stocke seulement l'URL de la couverture, sans télécharger l'image.
 * - Timeout configuré à 10 secondes pour éviter les blocages.
 */
final class OpenLibraryClient
{
    private const BASE_URL = 'https://openlibrary.org/api/books';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $contactEmail = 'contact@mediatheque.local',
    ) {}

    /**
     * Interroge Open Library pour un lot d'ISBN-13 (jusqu'à 20 par appel).
     * Renvoie une map ['ISBN:9780000000000' => BookDto].
     * Les ISBN non trouvés par Open Library ne figurent pas dans la map.
     *
     * @param  string[] $isbn13List Liste d'ISBN-13 normalisés (chiffres uniquement)
     * @return array<string, BookDto>
     * @throws \RuntimeException en cas d'erreur réseau ou réponse non-JSON
     */
    public function fetchBatch(array $isbn13List): array
    {
        if (empty($isbn13List)) {
            return [];
        }

        // Découper en paquets de 20 pour respecter les bonnes pratiques de l'API
        $chunks = array_chunk($isbn13List, 20);
        $results = [];

        foreach ($chunks as $chunk) {
            $bibkeys = implode(',', array_map(fn(string $isbn) => 'ISBN:' . $isbn, $chunk));

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL, [
                    'query' => [
                        'bibkeys' => $bibkeys,
                        'format'  => 'json',
                        'jscmd'   => 'data',
                    ],
                    'headers' => [
                        'User-Agent' => sprintf(
                            'MediathequeEMC/1.0 (%s)',
                            $this->contactEmail
                        ),
                    ],
                    'timeout' => 10,
                ]);

                $data = $response->toArray();

            } catch (TransportExceptionInterface $e) {
                throw new \RuntimeException(
                    'Impossible de joindre Open Library : ' . $e->getMessage(),
                    0,
                    $e
                );
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    'Réponse inattendue de Open Library : ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            foreach ($chunk as $isbn13) {
                $key = 'ISBN:' . $isbn13;

                if (!isset($data[$key])) {
                    // ISBN inconnu d'Open Library : on ne l'ajoute pas à la map
                    continue;
                }

                $results[$key] = $this->parseEntry($isbn13, $data[$key]);
            }
        }

        return $results;
    }

    /**
     * Extrait les champs pertinents d'une entrée Open Library.
     *
     * @param array<string, mixed> $entry
     */
    private function parseEntry(string $isbn13, array $entry): BookDto
    {
        $title = isset($entry['title']) ? trim((string) $entry['title']) : null;

        // Sous-titre concaténé au titre principal si présent
        if (!empty($entry['subtitle'])) {
            $title = $title . ' : ' . trim((string) $entry['subtitle']);
        }

        // Auteurs : liste de noms
        $authors = [];
        if (!empty($entry['authors']) && is_array($entry['authors'])) {
            foreach ($entry['authors'] as $author) {
                $name = trim((string) ($author['name'] ?? ''));
                if ($name !== '') {
                    $authors[] = $name;
                }
            }
        }

        // Éditeur : on prend le premier de la liste s'il y en a plusieurs
        $publisher = null;
        if (!empty($entry['publishers']) && is_array($entry['publishers'])) {
            $pub = $entry['publishers'][0] ?? null;
            if ($pub !== null) {
                $publisher = trim((string) ($pub['name'] ?? $pub));
            }
        }

        // Année de publication
        $publicationYear = null;
        if (!empty($entry['publish_date'])) {
            preg_match('/\d{4}/', (string) $entry['publish_date'], $m);
            $publicationYear = isset($m[0]) ? (int) $m[0] : null;
        }

        // URL de couverture (taille medium préférée)
        $coverUrl = null;
        if (!empty($entry['cover'])) {
            $coverUrl = $entry['cover']['medium']
                ?? $entry['cover']['large']
                ?? $entry['cover']['small']
                ?? null;
        }

        return new BookDto(
            isbn13:          $isbn13,
            title:           $title ?: null,
            authors:         $authors,
            publisher:       $publisher,
            publicationYear: $publicationYear,
            coverUrl:        $coverUrl,
        );
    }
}
