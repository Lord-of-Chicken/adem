<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Twig\Environment;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset')]
        private readonly RateLimiterFactory $passwordResetLimiter,
    ) {}

    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, TranslatorInterface $translator): Response
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
                'title' => $translator->trans('nav.login'),
                'intro' => $translator->trans('security.login_intro'),
                'submit' => $translator->trans('security.login_submit'),
                'forgot_link' => $translator->trans('security.forgot_password'),
                'register_hint' => $translator->trans('security.register_hint'),
                'register_link' => $translator->trans('registration.submit'),
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
    public function request(
        Request $request,
        UserRepository $userRepository,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Environment $twig,
        LoggerInterface $logger,
    ): Response {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Rate limiting : 3 tentatives / 30 min par IP
            $limiter = $this->passwordResetLimiter->create($request->getClientIp() ?? 'anonymous');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('danger', $translator->trans('security.rate_limited'));
                return $this->redirectToRoute('app_forgot_password_request');
            }

            // Générer un token unique
            $resetToken = Uuid::v4()->toRfc4122();
            $resetTokenExpiresAt = new \DateTimeImmutable('+1 hour');

            /** @var string */
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneByEmail($email);

            $deliveryFailed = false;

            if ($user) {
                // Stocker le hash du token (pas le token brut) pour protéger contre une fuite BDD
                $user->setResetToken(hash('sha256', $resetToken));
                $user->setResetTokenExpiresAt($resetTokenExpiresAt);
                $entityManager->flush();

                try {
                    $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                    /** @var string */
                    $contactEmail = $this->getParameter('app.contact_email');
                    /** @var string */
                    $userEmail = $user->getEmail();
                    $email = (new Email())
                        ->from($contactEmail)
                        ->to($userEmail)
                        ->subject($translator->trans('security.email_subject'))
                        ->text($translator->trans('security.email_text', ['%url%' => $resetUrl]))
                        ->html($twig->render('emails/reset_password.html.twig', ['reset_url' => $resetUrl]));

                    $mailer->send($email);
                } catch (TransportExceptionInterface $e) {
                    // L'envoi a échoué : on logge sans révéler que l'email existe.
                    $deliveryFailed = true;
                    $logger->error('Password reset email delivery failed', ['exception' => $e]);
                }
            }

            // Message identique que l'utilisateur existe ou non (anti-énumération).
            // En cas d'échec d'envoi, avertir l'utilisateur sans confirmer l'existence du compte.
            if ($deliveryFailed) {
                $this->addFlash('warning', $translator->trans('security.reset_send_failed'));
            } else {
                $this->addFlash('success', $translator->trans('security.reset_sent'));
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestForm' => $form->createView(),
            'page' => [
                'title' => $translator->trans('security.reset_title'),
                'intro' => $translator->trans('security.reset_intro'),
                'submit' => $translator->trans('security.reset_submit'),
                'back_link' => $translator->trans('security.back_to_login'),
            ],
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        // Comparer le hash du token reçu avec celui stocké en BDD
        $hashedToken = hash('sha256', $token);
        $user = $userRepository->findOneBy(['resetToken' => $hashedToken]);

        if (!$user || !$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', $translator->trans('security.reset_invalid'));
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hasher le nouveau mot de passe
            /** @var string */
            $plainPassword = $form->get('plainPassword')->getData();
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $plainPassword
            );

            $user->setPassword($encodedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('security.reset_success'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
            'page' => [
                'title' => $translator->trans('security.new_password_title'),
                'intro' => $translator->trans('security.new_password_intro'),
                'submit' => $translator->trans('security.new_password_submit'),
                'back_link' => $translator->trans('security.back_to_login'),
            ],
        ]);
    }
}