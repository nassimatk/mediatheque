<?php

namespace App\Command;

use App\Service\Circulation\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Expire les réservations non retirées dans le délai imparti et réassigne les exemplaires.',
)]
class ExpireReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->reservationService->expireOutdatedReservations();

        if ($count > 0) {
            $io->success(sprintf('%d réservation(s) échue(s) expirée(s) avec succès.', $count));
        } else {
            $io->info('Aucune réservation échue à expirer.');
        }

        return Command::SUCCESS;
    }
}
