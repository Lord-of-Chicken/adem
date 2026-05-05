<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class LocaleListener
{
    private string $defaultLocale;

    public function __construct(
        private RequestStack $requestStack,
        string $defaultLocale = 'en'
    ) {
        $this->defaultLocale = $defaultLocale;
    }

    #[AsEventListener(priority: 20)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        // Essayer de récupérer la locale depuis la session
        $locale = $request->getSession()->get('_locale', $this->defaultLocale);

        // Valider que la locale est supportée
        $supportedLocales = ['en', 'fr'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = $this->defaultLocale;
        }

        $request->setLocale($locale);
    }
}
