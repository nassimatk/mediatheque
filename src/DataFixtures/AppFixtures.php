<?php

namespace App\DataFixtures;

use App\Entity\Contributor;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\SubscriptionPlan;
use App\Entity\Work;
use App\Enum\ContributorRole;
use App\Enum\CopyStatus;
use App\Enum\EditionType;
use App\Enum\PenaltyType;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(20261113); // Reproductibilité des données

        // ==========================================
        // 1. SITES (les 2 médiathèques du réseau)
        // ==========================================
        $siteCentre = (new Site())->setName('Médiathèque Centre-Ville');
        $siteAlAmal = (new Site())->setName('Médiathèque Al Amal');
        $manager->persist($siteCentre);
        $manager->persist($siteAlAmal);
        $sites = [$siteCentre, $siteAlAmal];

        // ==========================================
        // 2. FORMULES D'ABONNEMENT
        // ==========================================
        $planDecouverte = (new SubscriptionPlan())
            ->setName('Découverte')
            ->setMaxLoans(3)
            ->setLoanDays(14)
            ->setMaxRenewals(1)
            ->setPenaltyCentsPerDay(150); // 1.50 MAD / jour

        $planStandard = (new SubscriptionPlan())
            ->setName('Standard')
            ->setMaxLoans(5)
            ->setLoanDays(21)
            ->setMaxRenewals(2)
            ->setPenaltyCentsPerDay(200); // 2.00 MAD / jour

        $planPassion = (new SubscriptionPlan())
            ->setName('Passion')
            ->setMaxLoans(8)
            ->setLoanDays(30)
            ->setMaxRenewals(2)
            ->setPenaltyCentsPerDay(200); // 2.00 MAD / jour

        $manager->persist($planDecouverte);
        $manager->persist($planStandard);
        $manager->persist($planPassion);
        $plans = [$planDecouverte, $planStandard, $planPassion];

        // ==========================================
        // 3. CATALOGUE : ŒUVRES, ÉDITIONS, CONTRIBUTEURS
        // ==========================================
        /** @var Edition[] $allEditions */
        $allEditions = [];
        /** @var Copy[] $allCopies */
        $allCopies = [];
        $barcodeIndex = 1;

        // A. Catalogue de référence (Titres et auteurs réels et variés)
        $curatedCatalog = [
            // Romans & Classiques
            [
                'work' => 'Le Petit Prince',
                'editions' => [
                    [
                        'title' => 'Le Petit Prince (Édition originale illustrée)',
                        'isbn' => '9782070612758',
                        'type' => EditionType::Book,
                        'publisher' => 'Gallimard Jeunesse',
                        'year' => 2007,
                        'contributors' => [
                            ['name' => 'Antoine de Saint-Exupéry', 'role' => ContributorRole::Author],
                        ],
                    ],
                    [
                        'title' => 'Le Petit Prince (Collection Folio)',
                        'isbn' => '9782070408504',
                        'type' => EditionType::Book,
                        'publisher' => 'Folio',
                        'year' => 1999,
                        'contributors' => [
                            ['name' => 'Antoine de Saint-Exupéry', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => "L'Étranger",
                'editions' => [
                    [
                        'title' => "L'Étranger",
                        'isbn' => '9782070360024',
                        'type' => EditionType::Book,
                        'publisher' => 'Folio',
                        'year' => 1972,
                        'contributors' => [
                            ['name' => 'Albert Camus', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => '1984',
                'editions' => [
                    [
                        'title' => '1984 (Nouvelle traduction)',
                        'isbn' => '9782072783036',
                        'type' => EditionType::Book,
                        'publisher' => 'Gallimard',
                        'year' => 2018,
                        'contributors' => [
                            ['name' => 'George Orwell', 'role' => ContributorRole::Author],
                            ['name' => 'Josée Kamoun', 'role' => ContributorRole::Translator],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Les Misérables',
                'editions' => [
                    [
                        'title' => 'Les Misérables (Tome 1 & 2)',
                        'isbn' => '9782266209564',
                        'type' => EditionType::Book,
                        'publisher' => 'Pocket',
                        'year' => 2010,
                        'contributors' => [
                            ['name' => 'Victor Hugo', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'La Nuit Sacrée',
                'editions' => [
                    [
                        'title' => 'La Nuit Sacrée (Prix Goncourt 1987)',
                        'isbn' => '9782020098489',
                        'type' => EditionType::Book,
                        'publisher' => 'Seuil',
                        'year' => 1987,
                        'contributors' => [
                            ['name' => 'Tahar Ben Jelloun', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Le Pain Nu',
                'editions' => [
                    [
                        'title' => 'Le Pain Nu',
                        'isbn' => '9782020056977',
                        'type' => EditionType::Book,
                        'publisher' => 'Seuil',
                        'year' => 1980,
                        'contributors' => [
                            ['name' => 'Mohamed Choukri', 'role' => ContributorRole::Author],
                            ['name' => 'Tahar Ben Jelloun', 'role' => ContributorRole::Translator],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Dune',
                'editions' => [
                    [
                        'title' => 'Dune (Le Cycle de Dune, Tome 1)',
                        'isbn' => '9782266320481',
                        'type' => EditionType::Book,
                        'publisher' => 'Pocket',
                        'year' => 2020,
                        'contributors' => [
                            ['name' => 'Frank Herbert', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Larmes de sable',
                'editions' => [
                    [
                        'title' => 'Larmes de sable',
                        'isbn' => '9782070773534',
                        'type' => EditionType::Book,
                        'publisher' => 'Gallimard',
                        'year' => 2005,
                        'contributors' => [
                            ['name' => 'Mahi Binebine', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],

            // Bandes dessinées & Mangas
            [
                'work' => 'Astérix le Gaulois',
                'editions' => [
                    [
                        'title' => 'Astérix le Gaulois (Album 1)',
                        'isbn' => '9782012101333',
                        'type' => EditionType::Comic,
                        'publisher' => 'Hachette',
                        'year' => 1999,
                        'contributors' => [
                            ['name' => 'René Goscinny', 'role' => ContributorRole::Author],
                            ['name' => 'Albert Uderzo', 'role' => ContributorRole::Illustrator],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Tintin au Tibet',
                'editions' => [
                    [
                        'title' => 'Tintin au Tibet',
                        'isbn' => '9782203001190',
                        'type' => EditionType::Comic,
                        'publisher' => 'Casterman',
                        'year' => 1960,
                        'contributors' => [
                            ['name' => 'Hergé', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Blacksad',
                'editions' => [
                    [
                        'title' => 'Blacksad : Quelque part entre les ombres',
                        'isbn' => '9782205049657',
                        'type' => EditionType::Comic,
                        'publisher' => 'Dargaud',
                        'year' => 2000,
                        'contributors' => [
                            ['name' => 'Juan Díaz Canales', 'role' => ContributorRole::Author],
                            ['name' => 'Juanjo Guarnido', 'role' => ContributorRole::Illustrator],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'One Piece',
                'editions' => [
                    [
                        'title' => 'One Piece - Tome 01 : Romance Dawn',
                        'isbn' => '9782723488525',
                        'type' => EditionType::Comic,
                        'publisher' => 'Glénat',
                        'year' => 2013,
                        'contributors' => [
                            ['name' => 'Eiichiro Oda', 'role' => ContributorRole::Author],
                        ],
                    ],
                ],
            ],

            // Cinéma / DVD
            [
                'work' => 'Inception',
                'editions' => [
                    [
                        'title' => 'Inception (Édition Spéciale DVD)',
                        'isbn' => '5051889045763',
                        'type' => EditionType::Dvd,
                        'publisher' => 'Warner Home Video',
                        'year' => 2010,
                        'contributors' => [
                            ['name' => 'Christopher Nolan', 'role' => ContributorRole::Director],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Interstellar',
                'editions' => [
                    [
                        'title' => 'Interstellar (DVD)',
                        'isbn' => '5051889476215',
                        'type' => EditionType::Dvd,
                        'publisher' => 'Paramount',
                        'year' => 2015,
                        'contributors' => [
                            ['name' => 'Christopher Nolan', 'role' => ContributorRole::Director],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Le Voyage de Chihiro',
                'editions' => [
                    [
                        'title' => 'Le Voyage de Chihiro (Studio Ghibli)',
                        'isbn' => '3388330035037',
                        'type' => EditionType::Dvd,
                        'publisher' => 'Walt Disney Studios',
                        'year' => 2002,
                        'contributors' => [
                            ['name' => 'Hayao Miyazaki', 'role' => ContributorRole::Director],
                        ],
                    ],
                ],
            ],
            [
                'work' => 'Parasite',
                'editions' => [
                    [
                        'title' => 'Parasite (Palme d’Or 2019)',
                        'isbn' => '3512392523293',
                        'type' => EditionType::Dvd,
                        'publisher' => 'The Jokers',
                        'year' => 2019,
                        'contributors' => [
                            ['name' => 'Bong Joon-ho', 'role' => ContributorRole::Director],
                        ],
                    ],
                ],
            ],
        ];

        // Cache des contributeurs pour éviter les doublons
        /** @var array<string, Contributor> $contributorCache */
        $contributorCache = [];

        foreach ($curatedCatalog as $item) {
            $work = (new Work())->setTitle($item['work']);
            $manager->persist($work);

            foreach ($item['editions'] as $edData) {
                $edition = (new Edition())
                    ->setWork($work)
                    ->setTitle($edData['title'])
                    ->setIsbn($edData['isbn'])
                    ->setType($edData['type'])
                    ->setPublisher($edData['publisher'])
                    ->setPublicationYear($edData['year']);
                $manager->persist($edition);
                $allEditions[] = $edition;

                foreach ($edData['contributors'] as $contribData) {
                    $cName = $contribData['name'];
                    if (!isset($contributorCache[$cName])) {
                        $contributor = (new Contributor())->setName($cName);
                        $manager->persist($contributor);
                        $contributorCache[$cName] = $contributor;
                    }
                    $editionContrib = (new EditionContributor())
                        ->setEdition($edition)
                        ->setContributor($contributorCache[$cName])
                        ->setRole($contribData['role']);
                    $manager->persist($editionContrib);
                }

                // 2 à 4 exemplaires par édition, répartis sur les 2 sites
                $copiesCount = $faker->numberBetween(2, 4);
                for ($k = 0; $k < $copiesCount; $k++) {
                    $copy = (new Copy())
                        ->setEdition($edition)
                        ->setSite($sites[$k % 2])
                        ->setBarcode(sprintf('MED-%06d', $barcodeIndex++))
                        ->setStatus(CopyStatus::Available);
                    $manager->persist($copy);
                    $allCopies[] = $copy;
                }
            }
        }

        // B. Complément généré avec Faker pour étoffer le catalogue (jusqu'à ~100 œuvres)
        $genres = [
            EditionType::Book->value => ['Le Mystère de', 'Les Secrets de', 'Chroniques de', 'Histoire de', 'Voyage au cœur de', 'L’Ombre de', 'Le Chant du'],
            EditionType::Comic->value => ['Les Aventures de', 'Mission à', 'Le Retour de', 'L’Attaque de', 'Panique à'],
            EditionType::Dvd->value => ['Au-delà de', 'La Cité des', 'Le Dernier jour de', 'Destin croisé de', 'Le Silence de'],
        ];

        $publishers = [
            EditionType::Book->value => ['Gallimard', 'Seuil', 'Albin Michel', 'Pocket', 'Flammarion', 'Actes Sud', 'Folio'],
            EditionType::Comic->value => ['Dargaud', 'Glénat', 'Casterman', 'Delcourt', 'Soleil', 'Dupuis'],
            EditionType::Dvd->value => ['Warner Bros', 'Universal Pictures', 'Pathé', 'Gaumont', 'StudioCanal'],
        ];

        for ($i = 1; $i <= 80; $i++) {
            $type = $faker->randomElement([EditionType::Book, EditionType::Book, EditionType::Comic, EditionType::Dvd]);
            $prefix = $faker->randomElement($genres[$type->value]);
            $subject = $faker->city . ' ' . $faker->word;
            $workTitle = ucfirst($prefix . ' ' . $subject);

            $work = (new Work())->setTitle($workTitle);
            $manager->persist($work);

            // 1 ou 2 éditions
            $editionsPerWork = ($type === EditionType::Book && $faker->boolean(25)) ? 2 : 1;
            for ($e = 1; $e <= $editionsPerWork; $e++) {
                $edTitle = $workTitle . ($editionsPerWork > 1 ? " (Édition $e)" : '');
                $edition = (new Edition())
                    ->setWork($work)
                    ->setTitle($edTitle)
                    ->setIsbn($faker->isbn13())
                    ->setType($type)
                    ->setPublisher($faker->randomElement($publishers[$type->value]))
                    ->setPublicationYear($faker->numberBetween(1990, 2025));
                $manager->persist($edition);
                $allEditions[] = $edition;

                // Auteur / Réalisateur
                $personName = $faker->name();
                if (!isset($contributorCache[$personName])) {
                    $contributor = (new Contributor())->setName($personName);
                    $manager->persist($contributor);
                    $contributorCache[$personName] = $contributor;
                }
                $role = match ($type) {
                    EditionType::Dvd => ContributorRole::Director,
                    EditionType::Comic => ContributorRole::Author,
                    EditionType::Book => ContributorRole::Author,
                };
                $edContrib = (new EditionContributor())
                    ->setEdition($edition)
                    ->setContributor($contributorCache[$personName])
                    ->setRole($role);
                $manager->persist($edContrib);

                // Pour les BD, ajouter parfois un illustrateur distinct
                if ($type === EditionType::Comic && $faker->boolean(50)) {
                    $illName = $faker->name();
                    if (!isset($contributorCache[$illName])) {
                        $illContrib = (new Contributor())->setName($illName);
                        $manager->persist($illContrib);
                        $contributorCache[$illName] = $illContrib;
                    }
                    $edContrib2 = (new EditionContributor())
                        ->setEdition($edition)
                        ->setContributor($contributorCache[$illName])
                        ->setRole(ContributorRole::Illustrator);
                    $manager->persist($edContrib2);
                }

                // 2 exemplaires par édition
                for ($k = 0; $k < 2; $k++) {
                    $copy = (new Copy())
                        ->setEdition($edition)
                        ->setSite($sites[$faker->numberBetween(0, 1)])
                        ->setBarcode(sprintf('MED-%06d', $barcodeIndex++))
                        ->setStatus(CopyStatus::Available);
                    $manager->persist($copy);
                    $allCopies[] = $copy;
                }
            }
        }

        // ==========================================
        // 4. ADHÉRENTS (MEMBERS)
        // ==========================================
        $firstNames = [
            'Youssef', 'Fatima', 'Mehdi', 'Sarah', 'Amine', 'Salma', 'Omar', 'Khadija',
            'Hamza', 'Imane', 'Karim', 'Nadia', 'Zineb', 'Hassan', 'Leila', 'Anas',
            'Clara', 'Thomas', 'Julien', 'Camille', 'Lucas', 'Emma', 'Alexandre', 'Inès'
        ];
        $lastNames = [
            'El Amrani', 'Benali', 'Idrissi', 'Berrada', 'Tazi', 'Alami', 'Chraibi',
            'Mansouri', 'Kabbaj', 'Belkacem', 'Dupont', 'Martin', 'Bernard', 'Robert', 'Petit'
        ];

        /** @var Member[] $members */
        $members = [];
        for ($m = 1; $m <= 30; $m++) {
            $fn = $faker->randomElement($firstNames);
            $ln = $faker->randomElement($lastNames);
            $member = (new Member())
                ->setCardNumber(sprintf('ADH-%04d', $m))
                ->setFirstName($fn)
                ->setLastName($ln)
                ->setEmail(sprintf('%s.%s%d@example.com', strtolower($fn), strtolower(str_replace(' ', '', $ln)), $m))
                ->setPlan($faker->randomElement($plans));
            $manager->persist($member);
            $members[] = $member;
        }

        // ==========================================
        // 5. CIRCULATION (PRÊTS, RETARDS, PÉNALITÉS)
        // ==========================================
        $now = new \DateTimeImmutable('2026-11-13 15:00:00');
        $copyCursor = 0;

        // A. Prêts historiques clôturés (retournés sans problème ou avec pénalité passée)
        for ($h = 0; $h < 40; $h++) {
            if (!isset($allCopies[$copyCursor])) {
                break;
            }
            $c = $allCopies[$copyCursor++];
            $m = $faker->randomElement($members);
            $daysAgo = $faker->numberBetween(30, 120);
            $borrowedAt = $now->sub(new \DateInterval("P{$daysAgo}D"));
            $loanDays = $m->getPlan()->getLoanDays();
            $dueAt = $borrowedAt->add(new \DateInterval("P{$loanDays}D"));

            // 80% retournés à l'heure, 20% avec retard
            $delay = $faker->boolean(20) ? $faker->numberBetween(2, 10) : 0;
            $closedAt = $dueAt->add(new \DateInterval("P{$delay}D"));

            $loan = (new Loan())
                ->setCopy($c)
                ->setMember($m)
                ->setBorrowedAt($borrowedAt)
                ->setDueAt($dueAt)
                ->setClosedAt($closedAt)
                ->setRenewalCount($faker->numberBetween(0, 1));
            $manager->persist($loan);

            if ($delay > 0) {
                // Pénalité de retard passée et payée
                $penalty = (new Penalty())
                    ->setLoan($loan)
                    ->setType(PenaltyType::Late)
                    ->setAmountCents($delay * $m->getPlan()->getPenaltyCentsPerDay())
                    ->setCreatedAt($closedAt)
                    ->setPaidAt($closedAt->add(new \DateInterval('P1D')));
                $manager->persist($penalty);
            }
        }

        // B. Prêts actifs en cours (non échus, exemplaires empruntés)
        for ($a = 0; $a < 18; $a++) {
            if (!isset($allCopies[$copyCursor])) {
                break;
            }
            $c = $allCopies[$copyCursor++];
            $m = $faker->randomElement($members);
            $borrowedDaysAgo = $faker->numberBetween(1, 10);
            $borrowedAt = $now->sub(new \DateInterval("P{$borrowedDaysAgo}D"));
            $dueAt = $borrowedAt->add(new \DateInterval("P{$m->getPlan()->getLoanDays()}D"));

            $loan = (new Loan())
                ->setCopy($c)
                ->setMember($m)
                ->setBorrowedAt($borrowedAt)
                ->setDueAt($dueAt)
                ->setClosedAt(null)
                ->setRenewalCount(0);
            $manager->persist($loan);

            $c->setStatus(CopyStatus::Borrowed);
        }

        // C. Prêts en retard actifs (non retournés, dueAt dépassé => Pénalités actives impayées)
        for ($r = 0; $r < 7; $r++) {
            if (!isset($allCopies[$copyCursor])) {
                break;
            }
            $c = $allCopies[$copyCursor++];
            $m = $faker->randomElement($members);
            $overdueDays = $faker->numberBetween(3, 15);
            $loanDays = $m->getPlan()->getLoanDays();
            $totalDaysAgo = $loanDays + $overdueDays;
            $borrowedAt = $now->sub(new \DateInterval("P{$totalDaysAgo}D"));
            $dueAt = $borrowedAt->add(new \DateInterval("P{$loanDays}D"));

            $loan = (new Loan())
                ->setCopy($c)
                ->setMember($m)
                ->setBorrowedAt($borrowedAt)
                ->setDueAt($dueAt)
                ->setClosedAt(null)
                ->setRenewalCount(0);
            $manager->persist($loan);

            $c->setStatus(CopyStatus::Borrowed);

            // Génération de la pénalité active impayée
            $amount = $overdueDays * $m->getPlan()->getPenaltyCentsPerDay();
            $penalty = (new Penalty())
                ->setLoan($loan)
                ->setType(PenaltyType::Late)
                ->setAmountCents($amount)
                ->setCreatedAt($dueAt->add(new \DateInterval('P1D')))
                ->setPaidAt(null); // Non payée !
            $manager->persist($penalty);
        }

        // D. Exemplaires abîmés ou perdus
        if (isset($allCopies[$copyCursor])) {
            $cDamaged = $allCopies[$copyCursor++];
            $cDamaged->setStatus(CopyStatus::Damaged);
        }
        if (isset($allCopies[$copyCursor])) {
            $cLost = $allCopies[$copyCursor++];
            $cLost->setStatus(CopyStatus::Lost);
        }

        // ==========================================
        // 6. RÉSERVATIONS
        // ==========================================
        // Réservation 1 : Petit Prince - En attente (Pending) par Member 1 au site Al Amal
        $res1 = (new Reservation())
            ->setMember($members[0])
            ->setEdition($allEditions[0])
            ->setPickupSite($siteAlAmal)
            ->setStatus(ReservationStatus::Pending)
            ->setCreatedAt($now->sub(new \DateInterval('P3D')));
        $manager->persist($res1);

        // Réservation 2 : Même édition (Petit Prince) - En attente (rang 2 dans la file FIFO)
        $res2 = (new Reservation())
            ->setMember($members[1])
            ->setEdition($allEditions[0])
            ->setPickupSite($siteCentre)
            ->setStatus(ReservationStatus::Pending)
            ->setCreatedAt($now->sub(new \DateInterval('P1D')));
        $manager->persist($res2);

        // Réservation 3 : 1984 - Prête pour retrait (Ready), exemplaire mis de côté 5 jours
        if (isset($allCopies[$copyCursor])) {
            $cReserved = $allCopies[$copyCursor++];
            $cReserved->setStatus(CopyStatus::Reserved);
            $res3 = (new Reservation())
                ->setMember($members[2])
                ->setEdition($allEditions[2])
                ->setPickupSite($siteCentre)
                ->setCopy($cReserved)
                ->setStatus(ReservationStatus::Ready)
                ->setCreatedAt($now->sub(new \DateInterval('P2D')))
                ->setHoldUntil($now->add(new \DateInterval('P3D'))); // Valable encore 3 jours
            $manager->persist($res3);
        }

        $manager->flush();
    }
}
