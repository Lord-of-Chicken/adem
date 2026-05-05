<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        // Sécurité : on empêche un utilisateur déjà connecté de se ré-inscrire
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Extraction du mot de passe en clair depuis le champ non-mappé du formulaire
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Hachage sécurisé
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            
            // On s'assure que l'utilisateur a au moins le rôle de base
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('registration.success'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'page' => [
                'title' => $translator->trans('registration.title'),
                'intro' => $translator->trans('registration.intro'),
                'submit' => $translator->trans('registration.submit'),
                'hint_prefix' => $translator->trans('registration.hint_prefix'),
                'hint_link' => $translator->trans('nav.login'),
            ],
        ]);
    }
}