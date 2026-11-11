<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postFlush)]
final class MediaCacheInvalidator
{
    public function postFlush(PostFlushEventArgs $event): void
    {
        $event->getObjectManager()->getConfiguration()->getResultCache()?->deleteItem('media_published');
    }
}
