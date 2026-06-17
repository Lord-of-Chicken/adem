<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the GDPR legal pages: privacy policy, legal mentions and cookie policy.
 *
 * Thin controller — these pages are static and fully driven by translations
 * and templates. The locale prefix (/fr, /en, /nl) is applied by
 * config/routes.yaml (locale_routes).
 */
final class LegalController extends AbstractController
{
    /**
     * Displays the privacy policy (politique de confidentialité).
     */
    #[Route('/politique-confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    /**
     * Displays the legal mentions (mentions légales).
     */
    #[Route('/mentions-legales', name: 'app_legal_mentions')]
    public function legalMentions(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }

    /**
     * Displays the cookie policy (politique cookies).
     */
    #[Route('/politique-cookies', name: 'app_cookies_policy')]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }
}
