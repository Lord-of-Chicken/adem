<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles locale switching for internationalization.
 */
final class LocaleController extends AbstractController
{
    /**
     * Switches the application locale and redirects to the appropriate page.
     *
     * @param Request $request The HTTP request
     * @param string $locale The locale to switch to (en, fr, or nl)
     * @return Response Redirect to the same page with new locale or home page
     */
    #[Route('/locale/{locale}', name: 'app_locale_switch', requirements: ['locale' => 'en|fr|nl'])]
    public function switchLocale(Request $request, string $locale): Response
    {
        $supportedLocales = ['en', 'fr', 'nl'];
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = 'fr';
        }

        $request->getSession()->set('_locale', $locale);
        $request->setLocale($locale);

        // Only honour the referer when it is internal (same host) — prevents open-redirect.
        $referer = $request->headers->get('referer');
        if ($referer) {
            $parts = parse_url($referer);
            $refererHost = $parts['host'] ?? null;
            $currentHost = $request->getHost();

            if ($refererHost === $currentHost) {
                $path = $parts['path'] ?? '/';
                // Force absolute, single-slash path to defeat protocol-relative tricks.
                $path = '/' . ltrim($path, '/');
                $newPath = preg_replace('#^/(en|fr|nl)(/|$)#', '/' . $locale . '$2', $path, 1);

                // If no locale prefix was found in the referer (e.g. admin pages),
                // fall back to home in the new locale rather than staying on the same page.
                if ($newPath === $path && !preg_match('#^/(en|fr|nl)(/|$)#', $path)) {
                    return $this->redirectToRoute('app_home', ['_locale' => $locale]);
                }

                $query = isset($parts['query']) ? '?' . $parts['query'] : '';

                return $this->redirect($newPath . $query);
            }
        }

        return $this->redirectToRoute('app_home', ['_locale' => $locale]);
    }
}
