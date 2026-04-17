<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // 1. Rediriger si l'utilisateur est déjà connecté (évite de voir le formulaire inutilement)
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Récupération de l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Dernier nom d'utilisateur saisi par l'internaute
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * 2. Route de déconnexion. 
     * Cette méthode peut rester vide, Symfony intercepte la route 
     * via la configuration du firewall dans security.yaml.
     */
    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut rester vide, elle sera interceptée par le logout du firewall.');
    }
}