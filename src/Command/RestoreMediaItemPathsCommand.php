<?php

namespace App\Command;

use App\Repository\MediaItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:restore-media-paths')]
class RestoreMediaItemPathsCommand extends Command
{
    public function __construct(
        private MediaItemRepository $mediaItemRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mediaItems = $this->mediaItemRepository->findAll();
        
        foreach ($mediaItems as $mediaItem) {
            $currentPath = $mediaItem->getAssetPath();
            
            // Add 'img/ruelle/' prefix if not present
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
