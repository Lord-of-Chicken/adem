<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

        // Redirect non-locale URLs to the default locale (fr) to avoid duplicate content
        $path = $request->getPathInfo();

        // Sitemap, Symfony dev tools and static assets must be accessible at root without redirect
        if ($path === '/sitemap.xml' || preg_match('#^/(_wdt|_profiler|assets|bundles|build|img|admin)(/|$)#', $path)) {
            return;
        }

        // Already has a locale prefix — let it through
        if (preg_match('#^/(en|fr|nl)(/|$)#', $path)) {
            if ($request->hasPreviousSession()) {
                /** @var string */
                $locale = $request->getSession()->get('_locale', $this->defaultLocale);

                if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
                    $locale = $this->defaultLocale;
                }

                $request->setLocale($locale);
            }

            return;
        }

        // Redirect non-locale URLs to the default locale version
        $targetPath = '/' . $this->defaultLocale . $path;
        $targetPath = rtrim($targetPath, '/') ?: '/' . $this->defaultLocale;

        $qs = $request->getQueryString();
        if ($qs !== null) {
            $targetPath .= '?' . $qs;
        }

        $event->setResponse(new RedirectResponse($targetPath, 301));
    }
}
