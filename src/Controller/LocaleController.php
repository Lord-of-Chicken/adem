<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
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
                $path = preg_replace('#^/(en|fr|nl)(/|$)#', '/' . $locale . '$2', $path, 1);

                $query = isset($parts['query']) ? '?' . $parts['query'] : '';

                return $this->redirect($path . $query);
            }
        }

        return $this->redirectToRoute('app_home', ['_locale' => $locale]);
    }
}
