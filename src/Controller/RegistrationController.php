<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\NewsletterSubscriptionService;
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
        NewsletterSubscriptionService $newsletterSubscription,
    ): Response {
        // Sécurité : on empêche un utilisateur déjà connecté de se ré-inscrire
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération d'un mot de passe aléatoire (l'utilisateur le définira via « mot de passe oublié »)
            /** @var string */
            $plainPassword = bin2hex(random_bytes(16));
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            // On s'assure que l'utilisateur a au moins le rôle de base
            $user->setRoles(['ROLE_USER']);

            // RGPD double opt-in : la case newsletter n'exprime qu'une intention.
            // Le flag n'est activé qu'après confirmation via le lien envoyé par e-mail.
            $wantsNewsletter = $user->isNewsletter();
            $user->setNewsletter(false);

            $entityManager->persist($user);
            $entityManager->flush();

            $message = $translator->trans('registration.success');
            if ($wantsNewsletter) {
                /** @var string $userEmail */
                $userEmail = $user->getEmail();
                $newsletterSubscription->startDoubleOptIn($userEmail);
                $message .= ' ' . $translator->trans('registration.newsletter_double_optin');
            }
            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'page' => [
                'title' => $translator->trans('registration.title'),
                'intro' => $translator->trans('registration.intro'),
                'submit' => $translator->trans('registration.submit'),
            ],
        ]);
    }
}