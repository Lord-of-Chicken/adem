<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Uid\Uuid;

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
            'page' => [
                'title' => 'Connexion',
                'intro' => 'Connecte-toi pour gérer ton panier de participations.',
                'submit' => 'Se connecter',
                'forgot_link' => 'Mot de passe oublié ?',
                'register_hint' => 'Pas encore de compte ?',
                'register_link' => 'Inscription',
            ],
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

    #[Route('/reset-password', name: 'app_forgot_password_request')]
    public function request(Request $request, UserRepository $userRepository, MailerInterface $mailer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un token unique
            $resetToken = Uuid::v4()->toRfc4122();
            $resetTokenExpiresAt = new \DateTimeImmutable('+1 hour');

            $user = $userRepository->findOneByEmail($form->get('email')->getData());

            if ($user) {
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt($resetTokenExpiresAt);
                $entityManager->flush();

                try {
                    $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                    $email = (new Email())
                        ->from('info@ruelledadem.com')
                        ->to($user->getEmail())
                        ->subject('Réinitialisation de votre mot de passe - La Ruelle d\'Adem')
                        ->text('Pour réinitialiser votre mot de passe, cliquez sur ce lien : ' . $resetUrl)
                        ->html('<p>Pour réinitialiser votre mot de passe, cliquez sur ce lien :</p>' .
                               '<p><a href="' . $resetUrl . '">Réinitialiser mon mot de passe</a></p>' .
                               '<p>Ce lien expire dans 1 heure.</p>');

                    $mailer->send($email);
                } catch (\Exception $e) {
                    // Email sending failed silently
                }
            }

            // Message identique que l'utilisateur existe ou non (sécurité)
            $this->addFlash('success', 'Si cet email existe, un lien de réinitialisation a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestForm' => $form->createView(),
            'page' => [
                'title' => 'Réinitialiser le mot de passe',
                'intro' => 'Entrez votre adresse e-mail pour recevoir un lien de réinitialisation.',
                'submit' => 'Envoyer le lien',
                'back_link' => 'Retour à la connexion',
            ],
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // Trouver l'utilisateur avec ce token valide
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hasher le nouveau mot de passe
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
            'page' => [
                'title' => 'Nouveau mot de passe',
                'intro' => 'Choisis ton nouveau mot de passe.',
                'submit' => 'Réinitialiser',
                'back_link' => 'Retour à la connexion',
            ],
        ]);
    }
}