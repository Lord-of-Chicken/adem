<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch')]
    public function switchLocale(Request $request, string $locale): Response
    {
        $supportedLocales = ['en', 'fr'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = 'en';
        }

        $request->getSession()->set('_locale', $locale);
        $request->setLocale($locale);

        $referer = $request->headers->get('referer');
        if ($referer) {
            $parts = parse_url($referer);
            $path = $parts['path'] ?? '/';
            $path = preg_replace('#^/(en|fr)(/|$)#', '/'.$locale.'$2', $path, 1);

            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return $this->redirect($path.$query);
        }

        return $this->redirectToRoute('app_home', ['_locale' => $locale]);
    }
}
