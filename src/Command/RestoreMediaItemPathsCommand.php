<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MediaItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to restore media item paths by adding 'img/ruelle/' prefix.
 */
#[AsCommand(name: 'app:restore-media-paths')]
final class RestoreMediaItemPathsCommand extends Command
{
    public function __construct(
        private readonly MediaItemRepository $mediaItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    /**
     * Executes the command to restore media item paths.
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     * @return int Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mediaItems = $this->mediaItemRepository->findAll();

        foreach ($mediaItems as $mediaItem) {
            $currentPath = $mediaItem->getAssetPath();
            if ($currentPath === null) {
                continue;
            }

            if (!str_starts_with($currentPath, 'img/ruelle/')) {
                $newPath = 'img/ruelle/' . $currentPath;
                $mediaItem->setAssetPath($newPath);
                $output->writeln("Restored: {$currentPath} -> {$newPath}");
            }
        }

        $this->entityManager->flush();
        $output->writeln('Media item paths restored successfully.');

        return Command::SUCCESS;
    }
}
