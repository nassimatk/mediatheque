<?php

namespace App\Controller\Member;

use App\Entity\Loan;
use App\Entity\User;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\LoanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
class MemberAreaController extends AbstractController
{
    #[Route('/espace-membre', name: 'app_member_dashboard')]
    public function dashboard(LoanRepository $loanRepository, ReservationRepository $reservationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $member = $user->getMember();

        if (!$member) {
            $this->addFlash('error', 'Aucune fiche adhérent associée à votre compte.');
            return $this->redirectToRoute('app_home');
        }

        $openLoans = $loanRepository->findOpenLoansForMember($member);
        
        $reservations = $reservationRepository->findBy(
            ['member' => $member, 'status' => [ReservationStatus::Pending, ReservationStatus::Ready]],
            ['createdAt' => 'ASC']
        );

        return $this->render('member/dashboard.html.twig', [
            'member'        => $member,
            'openLoans'     => $openLoans,
            'reservations'  => $reservations,
        ]);
    }

    #[Route('/espace-membre/prolonger/{id}', name: 'app_member_renew_loan', methods: ['POST'])]
    public function renew(Loan $loan, LoanService $loanService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $member = $user->getMember();

        if ($loan->getMember() !== $member) {
            throw $this->createAccessDeniedException();
        }

        try {
            $loanService->renew($loan);
            $this->addFlash('success', 'Votre prêt a été prolongé avec succès.');
        } catch (LoanException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_member_dashboard');
    }
}
