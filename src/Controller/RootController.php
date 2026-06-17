<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RootController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirect('/fr/', 301);
    }
}
