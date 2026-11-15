<?php

namespace App\Tests\Unit\Service;

use App\Entity\Contributor;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Work;
use App\Enum\ContributorRole;
use App\Enum\EditionType;
use App\Repository\ContributorRepository;
use App\Repository\EditionRepository;
use App\Repository\WorkRepository;
use App\Service\Integration\OpenLibrary\ImportResult;
use App\Service\Integration\OpenLibrary\IsbnImporter;
use App\Service\Integration\OpenLibrary\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests unitaires complets de IsbnImporter et OpenLibraryClient (Niveau 4).
 *
 * Tous les tests utilisent de fausses réponses HTTP (MockHttpClient / MockResponse)
 * et ne contactent JAMAIS l'API réelle d'Open Library.
 */
class IsbnImporterTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private EditionRepository&MockObject $editionRepo;
    private WorkRepository&MockObject $workRepo;
    private ContributorRepository&MockObject $contributorRepo;

    protected function setUp(): void
    {
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->editionRepo     = $this->createMock(EditionRepository::class);
        $this->workRepo        = $this->createMock(WorkRepository::class);
        $this->contributorRepo = $this->createMock(ContributorRepository::class);
    }

    private function createImporter(array $mockResponses = []): IsbnImporter
    {
        $mockHttpClient = new MockHttpClient($mockResponses);
        $client = new OpenLibraryClient($mockHttpClient, 'test@mediatheque.local');

        return new IsbnImporter(
            $client,
            $this->editionRepo,
            $this->workRepo,
            $this->contributorRepo,
            $this->em
        );
    }

    /**
     * Règle 1 : Un ISBN valide crée l'Édition, l'Œuvre et les Auteurs.
     */
    public function testValidIsbnCreatesEditionWorkAndAuthors(): void
    {
        // 978-2-07-036002-4 est un ISBN-13 valide (L'Étranger)
        $isbn13 = '9782070360024';
        $json = json_encode([
            'ISBN:' . $isbn13 => [
                'title' => "L'Étranger",
                'subtitle' => 'Roman',
                'authors' => [['name' => 'Albert Camus']],
                'publishers' => [['name' => 'Gallimard']],
                'publish_date' => 'May 1942',
                'cover' => ['medium' => 'https://covers.openlibrary.org/b/id/12345-M.jpg'],
            ],
        ]);

        $this->editionRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['isbn' => $isbn13])
            ->willReturn(null);

        $this->workRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['title' => "L'Étranger : Roman"])
            ->willReturn(null);

        $this->contributorRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'Albert Camus'])
            ->willReturn(null);

        $persisted = [];
        $this->em->expects($this->atLeast(3))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted) {
                $persisted[] = $entity;
            });

        $this->em->expects($this->once())->method('flush');

        $importer = $this->createImporter([new MockResponse($json)]);
        $results = $importer->import(['978-2-07-036002-4']);

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertSame(ImportResult::STATUS_CREATED, $result->status);
        $this->assertSame($isbn13, $result->isbn);
        $this->assertStringContainsString("L'Étranger", $result->title ?? '');

        // Vérification des entités persistées
        $work = null;
        $edition = null;
        $contributor = null;
        $editionContrib = null;

        foreach ($persisted as $entity) {
            if ($entity instanceof Work) {
                $work = $entity;
            } elseif ($entity instanceof Edition) {
                $edition = $entity;
            } elseif ($entity instanceof Contributor) {
                $contributor = $entity;
            } elseif ($entity instanceof EditionContributor) {
                $editionContrib = $entity;
            }
        }

        $this->assertNotNull($work, 'Une entité Work doit être persistée');
        $this->assertSame("L'Étranger : Roman", $work->getTitle());

        $this->assertNotNull($edition, 'Une entité Edition doit être persistée');
        $this->assertSame($isbn13, $edition->getIsbn());
        $this->assertSame('Gallimard', $edition->getPublisher());
        $this->assertSame(1942, $edition->getPublicationYear());
        $this->assertSame('https://covers.openlibrary.org/b/id/12345-M.jpg', $edition->getCoverUrl());
        $this->assertSame(EditionType::Book, $edition->getType());

        $this->assertNotNull($contributor, 'Une entité Contributor doit être persistée');
        $this->assertSame('Albert Camus', $contributor->getName());

        $this->assertNotNull($editionContrib, 'Une liaison EditionContributor doit être persistée');
        $this->assertSame(ContributorRole::Author, $editionContrib->getRole());
    }

    /**
     * Règle 2 : Un ISBN déjà présent en base est ignoré et rien n'est modifié (le catalogue fait foi).
     */
    public function testExistingIsbnIsSkippedWithoutCallingApi(): void
    {
        $isbn13 = '9782070360024';

        $existingEdition = new Edition();
        $existingEdition->setTitle('Titre Existant');
        $existingEdition->setIsbn($isbn13);

        $this->editionRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['isbn' => $isbn13])
            ->willReturn($existingEdition);

        // Aucune réponse MockResponse : si l'API est appelée, MockHttpClient lancera une exception
        $importer = $this->createImporter([]);
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $results = $importer->import([$isbn13]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_SKIPPED, $results[0]->status);
        $this->assertSame('Titre Existant', $results[0]->title);
    }

    /**
     * Règle 3 : Un ISBN invalide (mauvais format ou checksum faux) est rejeté sans appeler l'API.
     */
    public function testInvalidIsbnRejectedWithoutCallingApi(): void
    {
        // 9782070360029 a une clé de contrôle fausse (la vraie est 4)
        $invalidIsbn = '9782070360029';

        $importer = $this->createImporter([]);
        $this->editionRepo->expects($this->never())->method('findOneBy');
        $this->em->expects($this->never())->method('persist');

        $results = $importer->import([$invalidIsbn]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_FAILED, $results[0]->status);
        $this->assertStringContainsString('invalide', (string) $results[0]->message);
    }

    /**
     * Règle 4 : Un ISBN inconnu d'Open Library produit un échec avec message clair.
     */
    public function testUnknownIsbnInOpenLibraryResultsInFailure(): void
    {
        $isbn13 = '9782070360024';

        $this->editionRepo->method('findOneBy')->willReturn(null);

        // Open Library renvoie un objet JSON vide quand aucun livre ne correspond
        $importer = $this->createImporter([new MockResponse('{}')]);
        $this->em->expects($this->never())->method('persist');

        $results = $importer->import([$isbn13]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_FAILED, $results[0]->status);
        $this->assertStringContainsString('inconnu dans Open Library', (string) $results[0]->message);
    }

    /**
     * Règle 5 : Une réponse d'Open Library sans titre est rejetée avec un message clair.
     */
    public function testResponseWithoutTitleIsRejected(): void
    {
        $isbn13 = '9782070360024';

        $this->editionRepo->method('findOneBy')->willReturn(null);

        $json = json_encode([
            'ISBN:' . $isbn13 => [
                'authors' => [['name' => 'Auteur Inconnu']],
                'publishers' => [['name' => 'Éditeur']],
            ],
        ]);

        $importer = $this->createImporter([new MockResponse($json)]);
        $this->em->expects($this->never())->method('persist');

        $results = $importer->import([$isbn13]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_FAILED, $results[0]->status);
        $this->assertStringContainsString('ne fournit pas de titre', (string) $results[0]->message);
    }

    /**
     * Règle 6 : Une réponse sans éditeur ni couverture crée quand même la fiche avec champs vides.
     */
    public function testResponseWithoutPublisherOrCoverCreatesFicheWithNullFields(): void
    {
        $isbn13 = '9782070360024';

        $this->editionRepo->method('findOneBy')->willReturn(null);
        $this->workRepo->method('findOneBy')->willReturn(null);

        $json = json_encode([
            'ISBN:' . $isbn13 => [
                'title' => 'Livre Sans Editeur',
            ],
        ]);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $importer = $this->createImporter([new MockResponse($json)]);
        $results = $importer->import([$isbn13]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_CREATED, $results[0]->status);

        $edition = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Edition) {
                $edition = $entity;
            }
        }

        $this->assertNotNull($edition);
        $this->assertSame('Livre Sans Editeur', $edition->getTitle());
        $this->assertNull($edition->getPublisher());
        $this->assertNull($edition->getCoverUrl());
        $this->assertNull($edition->getPublicationYear());
    }

    /**
     * Règle 7 : Une panne réseau (TransportException) donne un échec propre sans plantage.
     */
    public function testNetworkErrorHandledGracefully(): void
    {
        $isbn13 = '9782070360024';

        $this->editionRepo->method('findOneBy')->willReturn(null);

        // Simuler une exception réseau
        $mockHttpClient = new MockHttpClient(function () {
            throw new TransportException('Connexion impossible vers openlibrary.org : timeout');
        });
        $client = new OpenLibraryClient($mockHttpClient);
        $importer = new IsbnImporter(
            $client,
            $this->editionRepo,
            $this->workRepo,
            $this->contributorRepo,
            $this->em
        );

        $results = $importer->import([$isbn13]);

        $this->assertCount(1, $results);
        $this->assertSame(ImportResult::STATUS_FAILED, $results[0]->status);
        $this->assertStringContainsString('Erreur réseau', (string) $results[0]->message);
    }

    /**
     * Règle 8 : Un lot mélangé donne un rapport exact pour chaque ISBN.
     */
    public function testMixedBatchProducesDetailedReport(): void
    {
        $isbnGood = '9782070360024'; // Bon
        $isbnExisting = '9780140449136'; // Déjà en base
        $isbnUnknown = '9782253006329'; // Inconnu d'Open Library
        $isbnInvalid = '12345'; // Format invalide

        $existingEdition = new Edition();
        $existingEdition->setTitle('Livre Déjà Présent');
        $existingEdition->setIsbn($isbnExisting);

        $this->editionRepo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($isbnExisting, $existingEdition) {
                if ($criteria['isbn'] === $isbnExisting) {
                    return $existingEdition;
                }
                return null;
            });

        // Open Library ne renvoie que pour $isbnGood (pas pour $isbnUnknown)
        $json = json_encode([
            'ISBN:' . $isbnGood => [
                'title' => 'Livre Importé avec Succès',
            ],
        ]);

        $importer = $this->createImporter([new MockResponse($json)]);

        $results = $importer->import([
            $isbnInvalid,
            $isbnExisting,
            $isbnGood,
            $isbnUnknown,
        ]);

        $this->assertCount(4, $results);

        // 1. Invalide
        $this->assertSame(ImportResult::STATUS_FAILED, $results[0]->status);
        $this->assertStringContainsString('invalide', (string) $results[0]->message);

        // 2. Déjà existant (skipped)
        $this->assertSame(ImportResult::STATUS_SKIPPED, $results[1]->status);
        $this->assertSame('Livre Déjà Présent', $results[1]->title);

        // 3. Créé avec succès
        $this->assertSame(ImportResult::STATUS_CREATED, $results[2]->status);
        $this->assertSame('Livre Importé avec Succès', $results[2]->title);

        // 4. Inconnu dans Open Library
        $this->assertSame(ImportResult::STATUS_FAILED, $results[3]->status);
        $this->assertStringContainsString('inconnu dans Open Library', (string) $results[3]->message);
    }

    /**
     * Test complémentaire : Normalisation d'un ISBN-10 vers un ISBN-13 (avec 'X' comme checksum).
     */
    public function testNormalizeIsbn10ToIsbn13(): void
    {
        $importer = $this->createImporter([]);

        // 0-471-00084-1 -> 978-0-471-00084-6
        $isbn13 = $importer->normalize('0-471-00084-1');
        $this->assertSame('9780471000846', $isbn13);

        // 0-8044-2957-X -> 978-0-8044-2957-3 (checksum 'X' = 10)
        $isbn13WithX = $importer->normalize('0-8044-2957-X');
        $this->assertSame('9780804429573', $isbn13WithX);
    }
}
