<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
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
