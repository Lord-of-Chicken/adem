<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MediaItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fix-media-paths')]
final class FixMediaItemPathsCommand extends Command
{
    public function __construct(
        private readonly MediaItemRepository $mediaItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mediaItems = $this->mediaItemRepository->findAll();

        foreach ($mediaItems as $mediaItem) {
            $currentPath = $mediaItem->getAssetPath();
            if ($currentPath === null) {
                continue;
            }

            $newPath = str_replace('img/ruelle/', '', $currentPath);

            if ($currentPath !== $newPath) {
                $mediaItem->setAssetPath($newPath);
                $output->writeln("Updated: {$currentPath} -> {$newPath}");
            }
        }

        $this->entityManager->flush();
        $output->writeln('Media item paths updated successfully.');

        return Command::SUCCESS;
    }
}
