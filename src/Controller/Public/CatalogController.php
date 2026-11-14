<?php

namespace App\Controller\Public;

use App\Entity\Edition;
use App\Entity\User;
use App\Enum\CopyStatus;
use App\Exception\ReservationException;
use App\Repository\EditionRepository;
use App\Repository\SiteRepository;
use App\Service\Circulation\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EditionRepository $editionRepository): Response
    {
        // 6 dernières nouveautés pour la page d'accueil
        $latestEditions = $editionRepository->findBy([], ['id' => 'DESC'], 6);

        return $this->render('public/home.html.twig', [
            'editions' => $latestEditions,
        ]);
    }

    #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(Request $request, EditionRepository $editionRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $type = $request->query->get('type');

        $qb = $editionRepository->createQueryBuilder('e')
            ->join('e.work', 'w')
            ->addSelect('w');

        if (!empty($search)) {
            $qb->andWhere('w.title LIKE :search OR e.isbn LIKE :search OR e.publisher LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($type)) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        $editions = $qb->orderBy('w.title', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        return $this->render('public/catalog/index.html.twig', [
            'editions' => $editions,
            'search'   => $search,
            'type'     => $type,
        ]);
    }

    #[Route('/catalogue/{id}', name: 'app_catalog_show', requirements: ['id' => '\d+'])]
    public function show(Edition $edition, SiteRepository $siteRepository): Response
    {
        $sites = $siteRepository->findAll();

        return $this->render('public/catalog/show.html.twig', [
            'edition' => $edition,
            'sites'   => $sites,
        ]);
    }

    #[Route('/catalogue/{id}/reserver', name: 'app_catalog_reserve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reserve(
        Edition $edition,
        Request $request,
        SiteRepository $siteRepository,
        ReservationService $reservationService
    ): Response {
        if (!$this->isGranted('ROLE_MEMBER')) {
            $this->addFlash('error', 'Vous devez être connecté en tant qu\'adhérent pour réserver un document.');
            return $this->redirectToRoute('app_login');
        }

        /** @var User $user */
        $user = $this->getUser();
        $member = $user->getMember();

        if (!$member) {
            $this->addFlash('error', 'Aucune fiche adhérent n\'est rattachée à votre compte.');
            return $this->redirectToRoute('app_catalog_show', ['id' => $edition->getId()]);
        }

        $siteId = $request->request->getInt('site_id');
        $pickupSite = $siteRepository->find($siteId);

        if (!$pickupSite) {
            $this->addFlash('error', 'Veuillez sélectionner un site de retrait valide.');
            return $this->redirectToRoute('app_catalog_show', ['id' => $edition->getId()]);
        }

        try {
            $reservationService->reserve($member, $edition, $pickupSite);
            $this->addFlash('success', sprintf(
                'Votre réservation pour "%s" a été enregistrée avec succès. Retrait prévu à la %s.',
                $edition->getWork()->getTitle(),
                $pickupSite->getName()
            ));

            return $this->redirectToRoute('app_member_dashboard');
        } catch (ReservationException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_catalog_show', ['id' => $edition->getId()]);
        }
    }
}
