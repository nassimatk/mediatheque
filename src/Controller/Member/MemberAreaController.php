<?php

namespace App\Controller\Member;

use App\Entity\Loan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\ReservationRepository;
use App\Service\Circulation\LoanService;
use App\Service\Circulation\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        $closedLoans = $loanRepository->findClosedLoansForMember($member);
        
        $reservations = $reservationRepository->findBy(
            ['member' => $member, 'status' => [ReservationStatus::Pending, ReservationStatus::Ready]],
            ['createdAt' => 'ASC']
        );

        return $this->render('member/dashboard.html.twig', [
            'member'        => $member,
            'openLoans'     => $openLoans,
            'closedLoans'   => $closedLoans,
            'reservations'  => $reservations,
        ]);
    }

    #[Route('/espace-membre/reservation/{id}/annuler', name: 'app_member_cancel_reservation', methods: ['POST'])]
    public function cancelReservation(
        Reservation $reservation,
        Request $request,
        ReservationService $reservationService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $member = $user->getMember();

        if ($reservation->getMember() !== $member) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_res_'.$reservation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $reservationService->cancel($reservation);
            $this->addFlash('success', 'Votre réservation a été annulée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_member_dashboard');
    }

    #[Route('/espace-membre/prolonger/{id}', name: 'app_member_renew_loan', methods: ['POST'])]
    public function renew(Loan $loan, Request $request, LoanService $loanService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $member = $user->getMember();

        if ($loan->getMember() !== $member) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('renew_'.$loan->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
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
