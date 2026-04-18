<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig');
    }

    #[Route('/profil/newsletter', name: 'app_profile_toggle_newsletter', methods: ['POST'])]
    public function toggleNewsletter(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('toggle_newsletter', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setNewsletter(!$user->isNewsletter());
        $entityManager->flush();

        $message = $user->isNewsletter() 
            ? 'Vous êtes maintenant inscrit à la newsletter.' 
            : 'Vous êtes maintenant désinscrit de la newsletter.';
        
        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profil/modifier', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profil/changer-mot-de-passe', name: 'app_profile_change_password')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Vérifier que le mot de passe actuel est correct
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            // Hasher et définir le nouveau mot de passe
            $encodedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($encodedPassword);

            $entityManager->flush();
            $this->addFlash('success', 'Votre mot de passe a été changé avec succès.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profil/supprimer', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_profile', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile');
        }

        // Déconnecter l'utilisateur avant suppression
        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        // Supprimer l'utilisateur (cascade delete supprimera les données liées)
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte a été supprimé avec succès.');

        return $this->redirectToRoute('app_home');
    }
}
