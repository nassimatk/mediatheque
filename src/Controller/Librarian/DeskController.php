<?php

namespace App\Controller\Librarian;

use App\Entity\Copy;
use App\Entity\Site;
use App\Enum\CopyStatus;
use App\Exception\LoanException;
use App\Exception\ReservationException;
use App\Repository\CopyRepository;
use App\Repository\LoanRepository;
use App\Repository\MemberRepository;
use App\Repository\SiteRepository;
use App\Service\Circulation\LoanService;
use App\Service\Circulation\ReservationService;
use App\Service\Integration\OpenLibrary\IsbnImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_LIBRARIAN')]
class DeskController extends AbstractController
{
    #[Route('/comptoir', name: 'app_librarian_desk', methods: ['GET'])]
    public function index(
        SiteRepository $siteRepository,
        LoanRepository $loanRepository,
        CopyRepository $copyRepository
    ): Response {
        $sites = $siteRepository->findBy([], ['name' => 'ASC']);
        
        // Liste des exemplaires actuellement en cours de transit entre sites
        $inTransitCopies = $copyRepository->findBy(
            ['status' => CopyStatus::InTransit],
            ['id' => 'DESC']
        );

        // 10 derniers prêts enregistrés pour référence rapide
        $recentLoans = $loanRepository->findBy(
            [],
            ['borrowedAt' => 'DESC'],
            10
        );

        return $this->render('librarian/desk.html.twig', [
            'sites'           => $sites,
            'inTransitCopies' => $inTransitCopies,
            'recentLoans'     => $recentLoans,
        ]);
    }

    #[Route('/comptoir/emprunt', name: 'app_librarian_desk_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        MemberRepository $memberRepository,
        CopyRepository $copyRepository,
        LoanService $loanService
    ): Response {
        if (!$this->isCsrfTokenValid('desk_checkout', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $cardNumber = trim($request->request->getString('cardNumber'));
        $barcode    = trim($request->request->getString('barcode'));

        if ($cardNumber === '' || $barcode === '') {
            $this->addFlash('error', 'Veuillez renseigner le numéro de carte et le code-barres.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $member = $memberRepository->findOneBy(['cardNumber' => $cardNumber]);
        if (!$member) {
            $this->addFlash('error', sprintf('Adhérent introuvable pour le n° de carte "%s".', $cardNumber));
            return $this->redirectToRoute('app_librarian_desk');
        }

        $copy = $copyRepository->findOneBy(['barcode' => $barcode]);
        if (!$copy) {
            $this->addFlash('error', sprintf('Exemplaire introuvable pour le code-barres "%s".', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        try {
            $loan = $loanService->checkout($member, $copy);
            $this->addFlash(
                'success',
                sprintf(
                    'Emprunt enregistré pour %s : « %s » (%s). Date limite de restitution : %s.',
                    $member->getFullName(),
                    $copy->getEdition()->getWork()->getTitle(),
                    $copy->getBarcode(),
                    $loan->getDueAt()->format('d/m/Y')
                )
            );
        } catch (LoanException $e) {
            $this->addFlash('error', 'Échec du prêt : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_librarian_desk');
    }

    #[Route('/comptoir/retour', name: 'app_librarian_desk_return', methods: ['POST'])]
    public function returnBook(
        Request $request,
        CopyRepository $copyRepository,
        LoanRepository $loanRepository,
        SiteRepository $siteRepository,
        LoanService $loanService
    ): Response {
        if (!$this->isCsrfTokenValid('desk_return', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $barcode   = trim($request->request->getString('barcode'));
        $siteId    = $request->request->getInt('siteId');
        $condition = $request->request->getString('condition', 'normal');

        if ($barcode === '') {
            $this->addFlash('error', 'Veuillez saisir le code-barres de l\'exemplaire à restituer.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $site = $siteRepository->find($siteId);
        if (!$site) {
            $this->addFlash('error', 'Veuillez sélectionner une médiathèque de retour valide.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $copy = $copyRepository->findOneBy(['barcode' => $barcode]);
        if (!$copy) {
            $this->addFlash('error', sprintf('Exemplaire introuvable avec le code-barres "%s".', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        $loan = $loanRepository->findOneBy(['copy' => $copy, 'closedAt' => null]);
        if (!$loan) {
            $this->addFlash('error', sprintf('Aucun prêt actif n\'a été trouvé pour l\'exemplaire %s.', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        $newStatus = match ($condition) {
            'damaged' => CopyStatus::Damaged,
            'lost'    => CopyStatus::Lost,
            default   => CopyStatus::Available,
        };

        try {
            $loanService->return($loan, $site, $newStatus);

            if ($copy->getStatus() === CopyStatus::InTransit) {
                $this->addFlash(
                    'warning',
                    sprintf(
                        'Retour enregistré pour « %s » (%s). ATTENTION : Document transféré en TRANSIT vers « %s » pour honorer une réservation en attente.',
                        $copy->getEdition()->getWork()->getTitle(),
                        $copy->getBarcode(),
                        $copy->getDestinationSite()?->getName() ?? 'autre site'
                    )
                );
            } elseif ($copy->getStatus() === CopyStatus::Reserved) {
                $this->addFlash(
                    'success',
                    sprintf(
                        'Retour enregistré pour « %s ». Document mis de côté au comptoir de « %s » pour un adhérent.',
                        $copy->getEdition()->getWork()->getTitle(),
                        $site->getName()
                    )
                );
            } elseif ($newStatus === CopyStatus::Damaged) {
                $this->addFlash('warning', sprintf('Retour enregistré. L\'exemplaire %s est marqué dégradé (forfait appliqué).', $copy->getBarcode()));
            } elseif ($newStatus === CopyStatus::Lost) {
                $this->addFlash('warning', sprintf('L\'exemplaire %s est déclaré perdu (forfait appliqué).', $copy->getBarcode()));
            } else {
                $this->addFlash(
                    'success',
                    sprintf(
                        'Retour enregistré avec succès pour « %s » (%s). Document remis en rayon à %s.',
                        $copy->getEdition()->getWork()->getTitle(),
                        $copy->getBarcode(),
                        $site->getName()
                    )
                );
            }
        } catch (LoanException $e) {
            $this->addFlash('error', 'Erreur lors du retour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_librarian_desk');
    }

    #[Route('/comptoir/reception-transit', name: 'app_librarian_desk_receive_transit', methods: ['POST'])]
    public function receiveTransit(
        Request $request,
        CopyRepository $copyRepository,
        SiteRepository $siteRepository,
        ReservationService $reservationService
    ): Response {
        if (!$this->isCsrfTokenValid('desk_receive_transit', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $barcode = trim($request->request->getString('barcode'));
        $siteId  = $request->request->getInt('siteId');

        if ($barcode === '') {
            $this->addFlash('error', 'Veuillez saisir le code-barres de l\'exemplaire réceptionné.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $site = $siteRepository->find($siteId);
        if (!$site) {
            $this->addFlash('error', 'Veuillez sélectionner la médiathèque de réception.');
            return $this->redirectToRoute('app_librarian_desk');
        }

        $copy = $copyRepository->findOneBy(['barcode' => $barcode]);
        if (!$copy) {
            $this->addFlash('error', sprintf('Exemplaire introuvable avec le code-barres "%s".', $barcode));
            return $this->redirectToRoute('app_librarian_desk');
        }

        try {
            $reservationService->receiveTransit($copy, $site);
            $this->addFlash(
                'success',
                sprintf(
                    'Transit validé ! L\'exemplaire « %s » (%s) est arrivé à %s. La réservation est prête (délai de retrait : 5 jours).',
                    $copy->getEdition()->getWork()->getTitle(),
                    $copy->getBarcode(),
                    $site->getName()
                )
            );
        } catch (ReservationException $e) {
            $this->addFlash('error', 'Erreur réception transit : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_librarian_desk');
    }

    // -------------------------------------------------------------------------
    // Niveau 4 — Import Open Library
    // -------------------------------------------------------------------------

    #[Route('/comptoir/import-isbn', name: 'app_librarian_desk_import_isbn', methods: ['GET', 'POST'])]
    public function importIsbn(Request $request, IsbnImporter $isbnImporter): Response
    {
        $results = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('desk_import_isbn', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $rawInput = $request->request->getString('isbns');
            // Découper en lignes, supprimer les vides
            $lines = array_filter(
                array_map('trim', preg_split('/\r?\n/', $rawInput) ?: []),
                fn(string $l) => $l !== ''
            );

            if (empty($lines)) {
                $this->addFlash('error', 'Veuillez saisir au moins un ISBN.');
            } else {
                $results = $isbnImporter->import(array_values($lines));

                $created = count(array_filter($results, fn($r) => $r->isCreated()));
                $skipped = count(array_filter($results, fn($r) => $r->isSkipped()));
                $failed  = count(array_filter($results, fn($r) => $r->isFailed()));

                if ($created > 0) {
                    $this->addFlash('success', sprintf(
                        '%d fiche(s) créée(s) avec succès. %d ignorée(s) (doublon). %d échec(s).',
                        $created, $skipped, $failed
                    ));
                } elseif ($skipped > 0 && $failed === 0) {
                    $this->addFlash('warning', sprintf(
                        'Tous les ISBN sont déjà présents dans le catalogue (%d doublon(s)).',
                        $skipped
                    ));
                } else {
                    $this->addFlash('error', sprintf(
                        'Aucune fiche créée. %d doublon(s), %d échec(s).',
                        $skipped, $failed
                    ));
                }
            }
        }

        return $this->render('librarian/import_isbn.html.twig', [
            'results' => $results,
        ]);
    }
}
