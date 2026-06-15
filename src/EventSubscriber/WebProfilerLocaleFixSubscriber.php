<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fixes the Symfony web profiler toolbar URLs when locale routes are used.
 *
 * In Symfony 8.0.12, the URL generator propagates _locale from the current
 * route context into _wdt and _profiler URL generations, producing broken
 * URLs like /fr/_wdt/{token} instead of /_wdt/{token}.
 *
 * This subscriber runs at priority -127 (just before the toolbar listener at -128)
 * and removes _locale from the URL generator context so the toolbar gets clean URLs.
 */
final class WebProfilerLocaleFixSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $context = $this->urlGenerator->getContext();
        $params = $context->getParameters();

        if (isset($params['_locale'])) {
            unset($params['_locale']);
            $context->setParameters($params);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -127],
        ];
    }
}
