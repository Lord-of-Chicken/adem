<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NewsletterConfirmationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes expired, never-confirmed newsletter tokens (GDPR data minimisation).
 *
 * Meant to be scheduled (cron), e.g. daily:
 *   php bin/console app:newsletter:purge-expired
 */
#[AsCommand(
    name: 'app:newsletter:purge-expired',
    description: 'Deletes expired unconfirmed newsletter double opt-in tokens',
)]
class PurgeNewsletterTokensCommand extends Command
{
    public function __construct(
        private NewsletterConfirmationRepository $repository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deleted = $this->repository->deleteExpiredUnconfirmed();

        $io->success(sprintf('%d expired unconfirmed newsletter token(s) purged.', $deleted));

        return Command::SUCCESS;
    }
}
