<?php

namespace App\Service\Integration\OpenLibrary;

use App\Entity\Contributor;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Work;
use App\Enum\ContributorRole;
use App\Enum\EditionType;
use App\Repository\ContributorRepository;
use App\Repository\EditionRepository;
use App\Repository\WorkRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service d'import ISBN : orchestre le client HTTP et la création des entités.
 *
 * Règles appliquées (Q5) :
 *   – Le catalogue fait foi : si l'ISBN existe déjà → ImportResult::skipped().
 *   – ISBN invalide (mauvais format/checksum) → ImportResult::failed() sans appel HTTP.
 *   – ISBN inconnu d'Open Library → ImportResult::failed().
 *   – Réponse sans titre → ImportResult::failed().
 *   – Éditeur ou couverture manquants → fiche créée quand même (champ null).
 *   – Réutilisation d'une Work de même titre si elle existe déjà.
 *   – On crée l'édition et l'œuvre, PAS les exemplaires physiques (D-015).
 */
final class IsbnImporter
{
    public function __construct(
        private readonly OpenLibraryClient    $client,
        private readonly EditionRepository    $editionRepository,
        private readonly WorkRepository       $workRepository,
        private readonly ContributorRepository $contributorRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Importe une liste d'ISBN bruts (avec ou sans tirets, ISBN-10 ou ISBN-13).
     *
     * @param  string[] $rawIsbns
     * @return ImportResult[]
     */
    public function import(array $rawIsbns): array
    {
        $results = [];

        // 1. Normaliser et valider chaque ISBN
        $validIsbns  = [];
        foreach ($rawIsbns as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            try {
                $isbn13 = $this->normalize($raw);
            } catch (\InvalidArgumentException $e) {
                $results[] = ImportResult::failed($raw, $e->getMessage());
                continue;
            }

            // 2. Vérifier si l'ISBN est déjà connu dans le catalogue
            $existing = $this->editionRepository->findOneBy(['isbn' => $isbn13]);
            if ($existing !== null) {
                $results[] = ImportResult::skipped($isbn13, $existing->getTitle());
                continue;
            }

            $validIsbns[$isbn13] = $raw;
        }

        if (empty($validIsbns)) {
            return $results;
        }

        // 3. Interroger Open Library en lot (1 requête groupée max 20 ISBN)
        try {
            $batch = $this->client->fetchBatch(array_keys($validIsbns));
        } catch (\RuntimeException $e) {
            foreach ($validIsbns as $isbn13 => $raw) {
                $results[] = ImportResult::failed($isbn13, 'Erreur réseau : ' . $e->getMessage());
            }
            return $results;
        }

        // 4. Créer les entités pour chaque ISBN récupéré
        foreach ($validIsbns as $isbn13 => $raw) {
            $key = 'ISBN:' . $isbn13;

            if (!isset($batch[$key])) {
                $results[] = ImportResult::failed($isbn13, 'ISBN inconnu dans Open Library.');
                continue;
            }

            $dto = $batch[$key];

            if ($dto->title === null || $dto->title === '') {
                $results[] = ImportResult::failed($isbn13, 'Open Library ne fournit pas de titre pour cet ISBN.');
                continue;
            }

            $edition = $this->createEdition($dto);
            $results[] = ImportResult::created($isbn13, $edition->getTitle());
        }

        $this->em->flush();

        return $results;
    }

    // -------------------------------------------------------------------------

    private function createEdition(BookDto $dto): Edition
    {
        // Réutiliser l'œuvre si elle existe déjà (même titre exact), sinon en créer une
        $work = $this->workRepository->findOneBy(['title' => $dto->title]);
        if ($work === null) {
            $work = new Work();
            $work->setTitle($dto->title);
            $this->em->persist($work);
        }

        $edition = new Edition();
        $edition
            ->setWork($work)
            ->setTitle($dto->title)
            ->setIsbn($dto->isbn13)
            ->setType(EditionType::Book)
            ->setPublisher($dto->publisher)
            ->setPublicationYear($dto->publicationYear)
            ->setCoverUrl($dto->coverUrl);

        $this->em->persist($edition);

        // Créer les auteurs (réutiliser si même nom exact)
        foreach ($dto->authors as $authorName) {
            $contributor = $this->contributorRepository->findOneBy(['name' => $authorName]);
            if ($contributor === null) {
                $contributor = new Contributor();
                $contributor->setName($authorName);
                $this->em->persist($contributor);
            }

            $link = new EditionContributor();
            $link->setEdition($edition)
                 ->setContributor($contributor)
                 ->setRole(ContributorRole::Author);

            $this->em->persist($link);
        }

        return $edition;
    }

    // -------------------------------------------------------------------------
    // Normalisation et validation de l'ISBN
    // -------------------------------------------------------------------------

    /**
     * Normalise un ISBN brut (tirets, espaces tolérés) en ISBN-13 (chiffres uniquement).
     *
     * @throws \InvalidArgumentException si l'ISBN est mal formé ou a un mauvais checksum
     */
    public function normalize(string $raw): string
    {
        // Supprimer tirets et espaces
        $clean = preg_replace('/[\s\-]/', '', $raw) ?? '';

        if (strlen($clean) === 10) {
            // Valider le checksum ISBN-10
            if (!$this->isValidIsbn10($clean)) {
                throw new \InvalidArgumentException(sprintf('ISBN-10 "%s" invalide (mauvais checksum).', $raw));
            }
            $clean = $this->isbn10ToIsbn13($clean);
        }

        if (strlen($clean) !== 13 || !ctype_digit($clean)) {
            throw new \InvalidArgumentException(sprintf('ISBN "%s" invalide (10 ou 13 chiffres attendus).', $raw));
        }

        if (!$this->isValidIsbn13($clean)) {
            throw new \InvalidArgumentException(sprintf('ISBN-13 "%s" invalide (mauvais checksum).', $raw));
        }

        return $clean;
    }

    private function isValidIsbn10(string $isbn): bool
    {
        if (strlen($isbn) !== 10) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (!ctype_digit($isbn[$i])) {
                return false;
            }
            $sum += (int) $isbn[$i] * (10 - $i);
        }
        $check = $isbn[9];
        $sum += ($check === 'X' || $check === 'x') ? 10 : (int) $check;
        return $sum % 11 === 0;
    }

    private function isValidIsbn13(string $isbn): bool
    {
        if (strlen($isbn) !== 13 || !ctype_digit($isbn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $isbn[12];
    }

    private function isbn10ToIsbn13(string $isbn10): string
    {
        $base = '978' . substr($isbn10, 0, 9);
        $sum  = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $base . $check;
    }
}
