<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NewsletterConfirmationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the GDPR double opt-in newsletter confirmation and unsubscription,
 * both reachable without authentication via a one-time token.
 */
final class NewsletterController extends AbstractController
{
    /**
     * Confirms a newsletter subscription from the emailed token.
     *
     * @param string $token The confirmation token
     * @param NewsletterConfirmationRepository $confirmationRepository The confirmation repository
     * @param UserRepository $userRepository The user repository
     * @param EntityManagerInterface $entityManager The entity manager
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to home with a flash message
     */
    #[Route('/newsletter/confirmer/{token}', name: 'app_newsletter_confirm', methods: ['GET'])]
    public function confirm(
        string $token,
        NewsletterConfirmationRepository $confirmationRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $confirmation = $confirmationRepository->findOneByToken($token);

        if ($confirmation === null || $confirmation->isExpired()) {
            $this->addFlash('danger', $translator->trans('newsletter.confirm_invalid'));
            return $this->redirectToRoute('app_home');
        }

        if (!$confirmation->isConfirmed()) {
            $confirmation->confirm();

            $user = $userRepository->findOneBy(['email' => $confirmation->getEmail()]);
            if ($user !== null && !$user->isAnonymized()) {
                $user->setNewsletter(true);
            }

            $entityManager->flush();
        }

        $this->addFlash('success', $translator->trans('newsletter.confirm_success'));
        return $this->redirectToRoute('app_home');
    }

    /**
     * Unsubscribes from the newsletter without requiring a login, via the token
     * embedded in the confirmation email.
     *
     * @param string $token The token tied to the subscriber email
     * @param NewsletterConfirmationRepository $confirmationRepository The confirmation repository
     * @param UserRepository $userRepository The user repository
     * @param EntityManagerInterface $entityManager The entity manager
     * @param TranslatorInterface $translator The translator service
     * @return Response Redirect to home with a flash message
     */
    #[Route('/newsletter/desinscription/{token}', name: 'app_newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        NewsletterConfirmationRepository $confirmationRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $confirmation = $confirmationRepository->findOneByToken($token);

        if ($confirmation === null) {
            $this->addFlash('danger', $translator->trans('newsletter.unsubscribe_invalid'));
            return $this->redirectToRoute('app_home');
        }

        $user = $userRepository->findOneBy(['email' => $confirmation->getEmail()]);
        if ($user !== null) {
            $user->setNewsletter(false);
            $entityManager->flush();
        }

        $this->addFlash('success', $translator->trans('newsletter.unsubscribe_success'));
        return $this->redirectToRoute('app_home');
    }
}
