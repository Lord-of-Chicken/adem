<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class FaqController extends AbstractController
{
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
