<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles user profile management including viewing, editing, newsletter toggle, password change, and account deletion.
 */
final class ProfileController extends AbstractController
{
    /**
     * Displays the user profile page.
     *
     * @param TranslatorInterface $translator The translator service
     * @return Response The profile page response
     */
    #[Route('/profil', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
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

    /**
     * Toggles the user's newsletter subscription.
     *
     * @param Request $request The HTTP request
     * @param EntityManagerInterface $entityManager The entity manager
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to profile page
     */
    #[Route('/profil/newsletter', name: 'app_profile_toggle_newsletter', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleNewsletter(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

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

    /**
     * Displays and processes the profile edit form.
     *
     * @param Request $request The HTTP request
     * @param EntityManagerInterface $entityManager The entity manager
     * @param TranslatorInterface $translator The translator service
     * @return Response The profile edit form or redirect
     */
    #[Route('/profil/modifier', name: 'app_profile_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

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

    /**
     * Displays and processes the password change form.
     *
     * @param Request $request The HTTP request
     * @param UserPasswordHasherInterface $passwordHasher The password hasher
     * @param EntityManagerInterface $entityManager The entity manager
     * @param TranslatorInterface $translator The translator service
     * @return Response The password change form or redirect
     */
    #[Route('/profil/changer-mot-de-passe', name: 'app_profile_change_password')]
    #[IsGranted('ROLE_USER')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

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

    /**
     * Handles the GDPR right to erasure: anonymises the account instead of a hard
     * delete so that the related orders (7-year fiscal retention) are preserved.
     *
     * @param Request $request The HTTP request
     * @param AccountAnonymizer $accountAnonymizer The account anonymisation service
     * @param TranslatorInterface $translator The translator service
     * @param TokenStorageInterface $tokenStorage The security token storage
     * @return Response Redirect to home page
     */
    #[Route('/profil/supprimer', name: 'app_profile_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, AccountAnonymizer $accountAnonymizer, TranslatorInterface $translator, TokenStorageInterface $tokenStorage): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_profile', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $translator->trans('profile.csrf_invalid'));
            return $this->redirectToRoute('app_profile');
        }

        // GDPR erasure: anonymise the personal data but keep the orders (legal
        // obligation), then invalidate the session so no active session stays
        // bound to the (now inactive) account.
        $accountAnonymizer->anonymize($user);

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', $translator->trans('profile.account_deleted'));

        return $this->redirectToRoute('app_home');
    }
}
