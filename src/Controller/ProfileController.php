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
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(TranslatorInterface $translator): Response
    {
        return $this->render('profile/index.html.twig', [
            'page' => [
                'title' => $translator->trans('profile.title'),
                'info_title' => $translator->trans('profile.info_title'),
                'notifications_title' => $translator->trans('profile.notifications_title'),
                'actions_title' => $translator->trans('profile.actions_title'),
                'edit_btn' => $translator->trans('profile.edit_btn'),
                'password_btn' => $translator->trans('profile.password_btn'),
                'logout_btn' => $translator->trans('profile.logout_btn'),
                'delete_btn' => $translator->trans('profile.delete_btn'),
                'delete_confirm' => $translator->trans('profile.delete_confirm'),
            ],
        ]);
    }

    #[Route('/profil/newsletter', name: 'app_profile_toggle_newsletter', methods: ['POST'])]
    public function toggleNewsletter(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('toggle_newsletter', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $translator->trans('profile.csrf_invalid'));
            return $this->redirectToRoute('app_profile');
        }

        $user->setNewsletter(!$user->isNewsletter());
        $entityManager->flush();

        $message = $user->isNewsletter()
            ? $translator->trans('profile.newsletter_subscribed')
            : $translator->trans('profile.newsletter_unsubscribed');

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profil/modifier', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('profile.updated'));

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'page' => [
                'title' => $translator->trans('profile.edit_btn'),
                'submit' => $translator->trans('profile.submit'),
                'cancel' => $translator->trans('profile.cancel'),
            ],
        ]);
    }

    #[Route('/profil/changer-mot-de-passe', name: 'app_profile_change_password')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Vérifier que le mot de passe actuel est correct
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', $translator->trans('profile.password_incorrect'));
                return $this->redirectToRoute('app_profile_change_password');
            }

            // Hasher et définir le nouveau mot de passe
            $encodedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($encodedPassword);

            $entityManager->flush();
            $this->addFlash('success', $translator->trans('profile.password_changed'));

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
            'page' => [
                'title' => $translator->trans('profile.password_btn'),
                'submit' => $translator->trans('profile.password'),
                'cancel' => $translator->trans('profile.cancel'),
            ],
        ]);
    }

    #[Route('/profil/supprimer', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_profile', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $translator->trans('profile.csrf_invalid'));
            return $this->redirectToRoute('app_profile');
        }

        // Supprimer l'utilisateur (cascade delete supprimera les données liées),
        // puis invalider la session pour éviter qu'une session active reste liée à un user purgé.
        $entityManager->remove($user);
        $entityManager->flush();

        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', $translator->trans('profile.account_deleted'));

        return $this->redirectToRoute('app_home');
    }
}
