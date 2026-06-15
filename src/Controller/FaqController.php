<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the FAQ (Frequently Asked Questions) page.
 */
class FaqController extends AbstractController
{
    /**
     * Displays the FAQ page.
     *
     * @param TranslatorInterface $translator The translator service
     * @return Response The FAQ page response
     */
    #[Route('/faq', name: 'app_faq')]
    public function index(TranslatorInterface $translator): Response
    {
        return $this->render('faq/index.html.twig', [
            'page' => [
                'title' => $translator->trans('faq.title'),
            ],
        ]);
    }
}
