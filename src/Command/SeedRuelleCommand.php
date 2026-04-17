<?php

namespace App\Command;

use App\Service\RuelleDatabaseSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-ruelle', description: 'Remplit formules, réglages du site et galerie médias (dossier assets/img/ruelle)')]
final class SeedRuelleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RuelleDatabaseSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->seeder->seed($this->entityManager);
        $io->success('Données Ruelle / participations / médias synchronisées.');

        return Command::SUCCESS;
    }
}
