<?php

namespace App\Tests\Integration;

use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Entity\Reservation;
use App\Entity\Site;
use App\Entity\Work;
use App\Enum\CopyStatus;
use App\Enum\PenaltyType;
use App\Enum\ReservationStatus;
use App\Repository\CopyRepository;
use App\Repository\MemberRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\LoanService;
use App\Service\Circulation\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration de bout en bout sur la base de données MySQL réelle.
 *
 * Valide sur de vraies tables SQL :
 *  1. Le cycle complet d'emprunt et de retour avec changement d'état de l'exemplaire.
 *  2. La file d'attente FIFO des réservations et la réattribution automatique au retour.
 *  3. Le blocage par pénalité et le déblocage immédiat après paiement au comptoir.
 *
 * Chaque test s'exécute dans une transaction SQL annulée (rollback) à la fin
 * afin de préserver l'état intact des données de test (fixtures).
 */
final class CirculationIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LoanService $loanService;
    private ReservationService $reservationService;
    private MemberRepository $memberRepository;
    private CopyRepository $copyRepository;
    private PenaltyRepository $penaltyRepository;
    private ReservationRepository $reservationRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->loanService = $container->get(LoanService::class);
        $this->reservationService = $container->get(ReservationService::class);
        $this->memberRepository = $container->get(MemberRepository::class);
        $this->copyRepository = $container->get(CopyRepository::class);
        $this->penaltyRepository = $container->get(PenaltyRepository::class);
        $this->reservationRepository = $container->get(ReservationRepository::class);

        // Démarrer une transaction pour isoler chaque test
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Annuler toutes les modifications pour laisser la base intacte
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Helper pour trouver un adhérent solvable (sans pénalité impayée).
     *
     * @param int[] $excludeIds
     */
    private function findSolventMember(array $excludeIds = []): Member
    {
        $members = $this->memberRepository->findAll();
        foreach ($members as $member) {
            if (in_array($member->getId(), $excludeIds, true)) {
                continue;
            }
            if (!$this->penaltyRepository->hasUnpaidPenalty($member)) {
                return $member;
            }
        }

        throw new \RuntimeException('Aucun adhérent solvable trouvé dans les fixtures.');
    }

    /**
     * Test 1 : Cycle complet Emprunt -> Enregistrement en base -> Restitution -> Remise en rayon.
     */
    public function testLoanAndReturnLifecycleOnRealDatabase(): void
    {
        $member = $this->findSolventMember();
        $copy = $this->copyRepository->findOneBy(['status' => CopyStatus::Available]);

        self::assertNotNull($member, 'Un adhérent solvable doit exister dans les fixtures.');
        self::assertNotNull($copy, 'Un exemplaire disponible doit exister dans les fixtures.');

        // 1. Enregistrer le prêt
        $loan = $this->loanService->checkout($member, $copy);

        self::assertNotNull($loan->getId(), 'Le prêt doit avoir un ID auto-incrémenté en base.');
        self::assertSame(CopyStatus::Borrowed, $copy->getStatus(), 'Le statut en base doit être Borrowed.');
        self::assertNull($loan->getClosedAt(), 'Le prêt actif ne doit pas avoir de date de clôture.');

        // 2. Enregistrer le retour
        $site = $copy->getSite();
        self::assertNotNull($site);

        $this->loanService->return($loan, $site, CopyStatus::Available);

        self::assertNotNull($loan->getClosedAt(), 'Le prêt doit être clôturé avec une date de retour.');
        self::assertSame(CopyStatus::Available, $copy->getStatus(), 'Le document doit être remis en rayon (Available).');
    }

    /**
     * Test 2 : File d'attente FIFO réelle entre deux adhérents sur une même édition.
     */
    public function testFifoQueuePriorityBetweenTwoReservationsOnReturn(): void
    {
        $site = $this->em->getRepository(Site::class)->findOneBy([]);
        self::assertNotNull($site);

        $borrower       = $this->findSolventMember();
        $reserverFirst  = $this->findSolventMember([$borrower->getId()]);
        $reserverSecond = $this->findSolventMember([$borrower->getId(), $reserverFirst->getId()]);

        // Créer une œuvre et une édition isolées pour ce test afin d'éviter toute interférence avec les réservations existantes
        $work = new Work();
        $work->setTitle('Œuvre Test Intégration FIFO ' . uniqid());
        $this->em->persist($work);

        $edition = new Edition();
        $edition->setWork($work)
                ->setTitle('Édition Test Intégration FIFO')
                ->setIsbn('979' . rand(1000000000, 9999999999));
        $this->em->persist($edition);

        $copy = new Copy();
        $copy->setEdition($edition)
             ->setSite($site)
             ->setBarcode('FIFO-' . uniqid())
             ->setStatus(CopyStatus::Available);
        $this->em->persist($copy);
        $this->em->flush();

        // 1. L'emprunteur initial prend l'exemplaire
        $loan = $this->loanService->checkout($borrower, $copy);

        // 2. Premier adhérent réserve l'édition à t0
        $res1 = $this->reservationService->reserve($reserverFirst, $edition, $site);
        self::assertSame(ReservationStatus::Pending, $res1->getStatus());

        // 3. Deuxième adhérent réserve la même édition à t1 (> t0)
        $res2 = $this->reservationService->reserve($reserverSecond, $edition, $site);
        self::assertSame(ReservationStatus::Pending, $res2->getStatus());

        // 4. Restitution du document au comptoir du site de retrait
        $this->loanService->return($loan, $site);

        // Rafraîchir les entités depuis la base MySQL
        $this->em->refresh($res1);
        $this->em->refresh($res2);
        $this->em->refresh($copy);

        // Règle FIFO : le premier réservant (res1) DOIT recevoir l'exemplaire et passer à Ready
        self::assertSame(ReservationStatus::Ready, $res1->getStatus(), 'La première réservation doit passer à Ready.');
        self::assertSame($copy->getId(), $res1->getCopy()?->getId(), 'L\'exemplaire doit être attribué au premier réservant.');
        self::assertNotNull($res1->getHoldUntil(), 'Une fenêtre de retrait de 5 jours doit être définie.');

        // Le deuxième réservant reste en file d'attente (Pending) sans exemplaire
        self::assertSame(ReservationStatus::Pending, $res2->getStatus(), 'La deuxième réservation doit rester Pending.');
        self::assertNull($res2->getCopy(), 'Aucun exemplaire ne doit être attribué au deuxième réservant.');
    }

    /**
     * Test 3 : Pénalité d'avarie constatée, blocage au prêt puis déblocage après paiement.
     */
    public function testPenaltyBlocksLoanUntilPaidAtDesk(): void
    {
        $member = $this->findSolventMember();
        $copy = $this->copyRepository->findOneBy(['status' => CopyStatus::Available]);

        self::assertNotNull($member);
        self::assertNotNull($copy);

        // Créer un prêt et le restituer avec dégradation (génère 20,00 DH de pénalité)
        $loan = $this->loanService->checkout($member, $copy);
        $this->loanService->return($loan, $copy->getSite(), CopyStatus::Damaged);

        // Vérifier que l'adhérent a une pénalité impayée en base
        self::assertTrue($this->penaltyRepository->hasUnpaidPenalty($member), 'L\'adhérent doit avoir une pénalité impayée.');

        // Récupérer la pénalité générée
        $penalty = $this->penaltyRepository->findOneBy(['loan' => $loan, 'paidAt' => null]);
        self::assertNotNull($penalty);
        self::assertSame(2000, $penalty->getAmountCents(), 'Le forfait de dégradation doit être de 2 000 centimes (20 DH).');

        // Payer la pénalité au comptoir
        $this->loanService->payPenalty($penalty);

        // Vérifier le déblocage en base
        self::assertNotNull($penalty->getPaidAt());
        self::assertFalse($this->penaltyRepository->hasUnpaidPenalty($member), 'L\'adhérent doit être débloqué après le paiement.');
    }
}
