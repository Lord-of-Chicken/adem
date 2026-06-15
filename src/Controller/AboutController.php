<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the "About Adem" page.
 */
final class AboutController extends AbstractController
{
    /**
     * Displays the "About Adem" page.
     *
     * @return Response The about page response
     */
    #[Route('/qui-est-adem', name: 'app_about')]
    public function index(): Response
    {
        return $this->render('about/index.html.twig', [
            'page' => [
                'title' => 'Qui est Adem?',
                'empty' => 'Contenu à venir...',
            ],
        ]);
    }
}
