<?php

namespace App\Controller\Public;

use App\Entity\Edition;
use App\Enum\CopyStatus;
use App\Repository\EditionRepository;
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
    public function show(Edition $edition): Response
    {
        return $this->render('public/catalog/show.html.twig', [
            'edition' => $edition,
        ]);
    }
}
