<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FaqController extends AbstractController
{
    #[Route('/{_locale}/faq', name: 'app_faq', requirements: ['_locale' => 'fr|en'])]
    public function index(): Response
    {
        return $this->render('faq/index.html.twig');
    }
}
