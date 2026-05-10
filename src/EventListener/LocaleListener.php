<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleListener
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'nl'];

    public function __construct(
        private readonly string $defaultLocale = 'fr',
    ) {
    }

    #[AsEventListener(priority: 20)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = (string) $request->getSession()->get('_locale', $this->defaultLocale);

        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = $this->defaultLocale;
        }

        $request->setLocale($locale);
    }
}
